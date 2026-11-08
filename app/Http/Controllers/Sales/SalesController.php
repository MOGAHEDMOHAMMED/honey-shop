<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\SaleApprovalRequest;
use App\Http\Requests\Sales\SalesRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Services\Sales\SalesApprovalService;
use App\Services\Sales\SalesDraftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'channel' => ['nullable', 'in:direct,external'],
            'status' => ['nullable', 'in:draft,approved'],
        ]);
        $search = trim($filters['search'] ?? '');
        $sales = SalesInvoice::query()
            ->with(['customer', 'businessTransaction'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('invoice_number', 'ilike', '%'.$search.'%')
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'ilike', '%'.$search.'%'));
                });
            })
            ->when($filters['channel'] ?? null, fn ($query, $channel) => $query->where('sales_channel', $channel))
            ->when($filters['status'] ?? null, function ($query, $status): void {
                $query->whereHas(
                    'businessTransaction',
                    fn ($transaction) => $transaction->where('status', $status),
                );
            })
            ->orderByDesc('transaction_id')
            ->paginate(15)
            ->withQueryString();

        return view('modules.sales.index', compact('sales', 'filters', 'search'));
    }

    public function create(): View
    {
        return view('modules.sales.create', $this->formData(null));
    }

    public function store(SalesRequest $request, SalesDraftService $service): RedirectResponse
    {
        $invoice = $service->create($request->validated());

        return redirect()->route('sales.show', $invoice)->with('success', __('catalog.messages.sale_draft_saved'));
    }

    public function show(SalesInvoice $sale): View
    {
        $sale->load([
            'customer',
            'businessTransaction.creator',
            'businessTransaction.approver',
            'lines.product.category',
        ]);
        $stock = DB::table('inventory_balances')
            ->whereIn('product_id', $sale->lines->pluck('product_id'))
            ->pluck('quantity', 'product_id');
        $deliveryProvider = $sale->delivery_provider_id
            ? DB::table('delivery_providers')->where('id', $sale->delivery_provider_id)->value('name')
            : null;
        $representative = $sale->representative_id
            ? DB::table('representatives')->where('id', $sale->representative_id)->value('name')
            : null;

        return view('modules.sales.show', compact('sale', 'stock', 'deliveryProvider', 'representative'));
    }

    public function edit(SalesInvoice $sale): View
    {
        $sale->load(['businessTransaction', 'lines']);
        abort_unless($sale->businessTransaction->status === 'draft', 404);

        return view('modules.sales.edit', $this->formData($sale));
    }

    public function update(
        SalesRequest $request,
        SalesInvoice $sale,
        SalesDraftService $service,
    ): RedirectResponse {
        $service->update($sale, $request->validated());

        return redirect()->route('sales.show', $sale)->with('success', __('catalog.messages.sale_draft_saved'));
    }

    public function approve(
        SaleApprovalRequest $request,
        SalesInvoice $sale,
        SalesApprovalService $service,
    ): RedirectResponse {
        $approved = $service->approve($sale, $request->validated('series_category'));

        return redirect()
            ->route('sales.show', $approved)
            ->with('success', __('catalog.messages.sale_approved'));
    }

    private function formData(?SalesInvoice $sale): array
    {
        $customers = Customer::query()
            ->where('is_active', true)
            ->when($sale, fn ($query) => $query->orWhereKey($sale->customer_id))
            ->orderBy('name')
            ->get();
        $products = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->when($sale, fn ($query) => $query->orWhereIn('id', $sale->lines->pluck('product_id')))
            ->orderBy('name')
            ->get();
        $providers = DB::table('delivery_providers')
            ->where('is_active', true)
            ->when($sale, fn ($query) => $query->orWhere('id', $sale->delivery_provider_id))
            ->orderBy('name')
            ->get();
        $representatives = DB::table('representatives')
            ->where('is_active', true)
            ->when($sale, fn ($query) => $query->orWhere('id', $sale->representative_id))
            ->orderBy('name')
            ->get();
        $lines = $sale
            ? $sale->lines->map(fn ($line): array => [
                'product_id' => $line->product_id,
                'entered_quantity' => $line->entered_quantity,
                'input_unit' => $line->input_unit,
                'unit_price' => $line->unit_price,
                'discount_percentage' => bcmul((string) $line->discount_rate, '100', 4),
            ])->all()
            : [[
                'product_id' => '',
                'entered_quantity' => '',
                'input_unit' => '',
                'unit_price' => '',
                'discount_percentage' => '0',
            ]];
        if (old('lines') !== null) {
            $lines = old('lines');
        }

        return compact('sale', 'customers', 'products', 'providers', 'representatives', 'lines');
    }
}
