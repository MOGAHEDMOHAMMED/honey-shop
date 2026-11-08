<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\CustomerRequest;
use App\Models\Customer;
use App\Services\Customers\CustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
        $search = trim($filters['search'] ?? '');
        $customers = Customer::query()
            ->withCount('sales')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('phone', 'ilike', '%'.$search.'%');
            }))
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('is_active', $status === 'active'),
            )
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
        $balances = DB::table('customer_account_entries')
            ->whereIn('customer_id', $customers->pluck('id'))
            ->where('currency_code', 'YER')
            ->selectRaw('customer_id, SUM(debit - credit) AS balance')
            ->groupBy('customer_id')
            ->pluck('balance', 'customer_id');

        return view('modules.customers.index', compact('customers', 'balances', 'filters', 'search'));
    }

    public function create(): View
    {
        return view('modules.customers.create', [
            'customer' => new Customer(['is_active' => true]),
        ]);
    }

    public function store(CustomerRequest $request, CustomerService $service): RedirectResponse
    {
        $customer = $service->create($request->validated());

        return redirect()->route('customers.show', $customer)->with('success', __('catalog.messages.customer_created'));
    }

    public function show(Customer $customer): View
    {
        $sales = $customer->sales()
            ->join(
                'business_transactions',
                'business_transactions.id',
                '=',
                'sales_invoices.transaction_id',
            )
            ->select('sales_invoices.*')
            ->with('businessTransaction')
            ->orderByDesc('business_transactions.occurred_at')
            ->paginate(10, ['*'], 'sales_page');
        $entries = $customer->accountEntries()
            ->with('transaction')
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'ledger_page');
        $balance = DB::table('customer_account_entries')
            ->where('customer_id', $customer->getKey())
            ->where('currency_code', 'YER')
            ->selectRaw('COALESCE(SUM(debit - credit), 0) AS balance')
            ->value('balance');

        return view('modules.customers.show', compact('customer', 'sales', 'entries', 'balance'));
    }

    public function edit(Customer $customer): View
    {
        return view('modules.customers.edit', compact('customer'));
    }

    public function update(
        CustomerRequest $request,
        Customer $customer,
        CustomerService $service,
    ): RedirectResponse {
        $service->update($customer, $request->validated());

        return redirect()->route('customers.show', $customer)->with('success', __('catalog.messages.customer_updated'));
    }
}
