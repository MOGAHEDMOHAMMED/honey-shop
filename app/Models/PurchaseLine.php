<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseLine extends Model
{
    protected $table = 'purchase_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'input_unit',
        'conversion_factor',
        'entered_quantity',
        'purchase_unit_price',
    ];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:6',
            'entered_quantity' => 'decimal:3',
            'purchase_unit_price' => 'decimal:2',
            'base_quantity' => 'decimal:3',
            'effective_base_unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
            'returned_quantity' => 'decimal:3',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'transaction_id', 'transaction_id');
    }
}
