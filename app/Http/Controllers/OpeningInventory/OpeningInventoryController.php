<?php

namespace App\Http\Controllers\OpeningInventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpeningInventory\OpeningInventoryRequest;
use App\Models\OpeningInventory;
use App\Models\Product;
use App\Services\OpeningInventory\OpeningInventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OpeningInventoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:draft,approved'],
        ]);

        $documents = OpeningInventory::query()
            ->join(
                'business_transactions',
                'business_transactions.id',
                '=',
                'opening_inventory.transaction_id',
            )
            ->select('opening_inventory.*')
            ->with('businessTransaction')
            ->withCount('lines')
            ->when($filters['status'] ?? null, function ($query, $status): void {
                $query->whereHas(
                    'businessTransaction',
                    fn ($transaction) => $transaction->where('status', $status),
                );
            })
            ->orderByDesc('business_transactions.occurred_at')
            ->paginate(15)
            ->withQueryString();

        return view('modules.opening-inventory.index', compact('documents', 'filters'));
    }

    public function create(): View
    {
        $approvedDocument = OpeningInventory::query()
            ->whereHas(
                'businessTransaction',
                fn ($transaction) => $transaction->where('status', 'approved'),
            )
            ->first();
        $alreadyApproved = $approvedDocument !== null;
        $products = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $lines = [['product_id' => '', 'entered_quantity' => '', 'input_unit' => '']];

        return view(
            'modules.opening-inventory.create',
            compact('alreadyApproved', 'approvedDocument', 'products', 'lines'),
        );
    }

    public function store(
        OpeningInventoryRequest $request,
        OpeningInventoryService $service,
    ): RedirectResponse {
        $document = $service->create($request->validated());

        return redirect()
            ->route('opening-inventory.show', $document)
            ->with('success', __('catalog.messages.opening_inventory_saved'));
    }

    public function show(OpeningInventory $openingInventory): View
    {
        $openingInventory->load([
            'businessTransaction.creator',
            'businessTransaction.approver',
            'lines.product.category',
        ]);
        $stock = DB::table('inventory_balances')
            ->whereIn('product_id', $openingInventory->lines->pluck('product_id'))
            ->pluck('quantity', 'product_id');

        return view(
            'modules.opening-inventory.show',
            compact('openingInventory', 'stock'),
        );
    }

    public function edit(OpeningInventory $openingInventory): View
    {
        $openingInventory->load(['businessTransaction', 'lines']);
        abort_unless($openingInventory->businessTransaction->status === 'draft', 404);

        $products = Product::query()
            ->with('category')
            ->where('is_active', true)
            ->orWhereIn('id', $openingInventory->lines->pluck('product_id'))
            ->orderBy('name')
            ->get();
        $lines = $openingInventory->lines->map(fn ($line): array => [
            'product_id' => $line->product_id,
            'entered_quantity' => $line->entered_quantity,
            'input_unit' => $line->input_unit,
        ])->all();

        if (old('lines') !== null) {
            $lines = old('lines');
        }

        return view(
            'modules.opening-inventory.edit',
            compact('openingInventory', 'products', 'lines'),
        );
    }

    public function update(
        OpeningInventoryRequest $request,
        OpeningInventory $openingInventory,
        OpeningInventoryService $service,
    ): RedirectResponse {
        $service->update($openingInventory, $request->validated());

        return redirect()
            ->route('opening-inventory.show', $openingInventory)
            ->with('success', __('catalog.messages.opening_inventory_saved'));
    }

    public function approve(
        OpeningInventory $openingInventory,
        OpeningInventoryService $service,
    ): RedirectResponse {
        $service->approve($openingInventory);

        return redirect()
            ->route('opening-inventory.show', $openingInventory)
            ->with('success', __('catalog.messages.opening_inventory_approved'));
    }
}
