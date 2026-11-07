<?php

namespace App\Http\Controllers\Suppliers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Suppliers\SupplierRequest;
use App\Models\Supplier;
use App\Models\SupplierType;
use App\Services\Suppliers\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'uuid'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
        $search = trim($filters['search'] ?? '');

        $suppliers = Supplier::query()
            ->with('type')
            ->withCount('purchases')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'ilike', '%'.$search.'%')
                        ->orWhere('phone', 'ilike', '%'.$search.'%');
                });
            })
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('supplier_type_id', $type))
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('is_active', $status === 'active'),
            )
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $types = SupplierType::query()->orderBy('name')->get();

        return view('modules.suppliers.index', compact('suppliers', 'types', 'filters', 'search'));
    }

    public function create(): View
    {
        $types = SupplierType::query()->where('is_active', true)->orderBy('name')->get();
        $supplier = new Supplier(['is_active' => true]);

        return view('modules.suppliers.create', compact('supplier', 'types'));
    }

    public function store(SupplierRequest $request, SupplierService $service): RedirectResponse
    {
        $supplier = $service->create($request->validated());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', __('catalog.messages.supplier_created'));
    }

    public function show(Supplier $supplier): View
    {
        $supplier->load('type');
        $purchases = $supplier->purchases()
            ->join(
                'business_transactions',
                'business_transactions.id',
                '=',
                'purchase_invoices.transaction_id',
            )
            ->select('purchase_invoices.*')
            ->with('businessTransaction')
            ->orderByDesc('business_transactions.occurred_at')
            ->paginate(10, ['*'], 'purchases_page');
        $entries = $supplier->accountEntries()
            ->with('transaction')
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'ledger_page');
        $balance = DB::table('supplier_account_entries')
            ->where('supplier_id', $supplier->getKey())
            ->where('currency_code', 'YER')
            ->selectRaw('COALESCE(SUM(credit - debit), 0) AS balance')
            ->value('balance');

        return view('modules.suppliers.show', compact('supplier', 'purchases', 'entries', 'balance'));
    }

    public function edit(Supplier $supplier): View
    {
        $types = SupplierType::query()
            ->where('is_active', true)
            ->orWhereKey($supplier->supplier_type_id)
            ->orderBy('name')
            ->get();

        return view('modules.suppliers.edit', compact('supplier', 'types'));
    }

    public function update(
        SupplierRequest $request,
        Supplier $supplier,
        SupplierService $service,
    ): RedirectResponse {
        $service->update($supplier, $request->validated());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', __('catalog.messages.supplier_updated'));
    }
}
