<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchases\PurchaseRequest;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Services\Purchases\PurchaseApprovalService;
use App\Services\Purchases\PurchaseDraftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,approved'],
        ]);
        $search = trim($filters['search'] ?? '');

        $purchases = PurchaseInvoice::query()
            ->with(['supplier', 'businessTransaction'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('invoice_number', 'ilike', '%'.$search.'%')
                        ->orWhere('supplier_invoice_number', 'ilike', '%'.$search.'%')
                        ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'ilike', '%'.$search.'%'));
                });
            })
            ->when($filters['status'] ?? null, function ($query, $status): void {
                $query->whereHas(
                    'businessTransaction',
                    fn ($transaction) => $transaction->where('status', $status),
                );
            })
            ->orderByDesc('transaction_id')
            ->paginate(15)
            ->withQueryString();

        return view('modules.purchases.index', compact('purchases', 'filters', 'search'));
    }

    public function create(): View
    {
        $suppliers = Supplier::query()->where('is_active', true)->orderBy('name')->get();
        $products = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $lines = [['product_id' => '', 'entered_quantity' => '', 'input_unit' => '', 'purchase_unit_price' => '']];

        return view('modules.purchases.create', compact('suppliers', 'products', 'lines'));
    }

    public function store(
        PurchaseRequest $request,
        PurchaseDraftService $service,
    ): RedirectResponse {
        $invoice = $service->create($request->validated());

        return redirect()
            ->route('purchases.show', $invoice)
            ->with('success', __('catalog.messages.purchase_draft_saved'));
    }

    public function show(PurchaseInvoice $purchase): View
    {
        $purchase->load([
            'supplier.type',
            'businessTransaction.creator',
            'businessTransaction.approver',
            'lines.product.category',
        ]);
        $stock = DB::table('inventory_balances')
            ->whereIn('product_id', $purchase->lines->pluck('product_id'))
            ->pluck('quantity', 'product_id');

        return view('modules.purchases.show', compact('purchase', 'stock'));
    }

    public function edit(PurchaseInvoice $purchase): View
    {
        $purchase->load(['businessTransaction', 'lines']);
        abort_unless($purchase->businessTransaction->status === 'draft', 404);

        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->orWhereKey($purchase->supplier_id)
            ->orderBy('name')
            ->get();
        $products = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->orWhereIn('id', $purchase->lines->pluck('product_id'))
            ->orderBy('name')
            ->get();
        $lines = $purchase->lines->map(fn ($line): array => [
            'product_id' => $line->product_id,
            'entered_quantity' => $line->entered_quantity,
            'input_unit' => $line->input_unit,
            'purchase_unit_price' => $line->purchase_unit_price,
        ])->all();

        if (old('lines') !== null) {
            $lines = old('lines');
        }

        return view('modules.purchases.edit', compact('purchase', 'suppliers', 'products', 'lines'));
    }

    public function update(
        PurchaseRequest $request,
        PurchaseInvoice $purchase,
        PurchaseDraftService $service,
    ): RedirectResponse {
        $service->update($purchase, $request->validated());

        return redirect()
            ->route('purchases.show', $purchase)
            ->with('success', __('catalog.messages.purchase_draft_saved'));
    }

    public function approve(
        PurchaseInvoice $purchase,
        PurchaseApprovalService $service,
    ): RedirectResponse {
        $service->approve($purchase);

        return redirect()
            ->route('purchases.show', $purchase)
            ->with('success', __('catalog.messages.purchase_approved'));
    }
}
