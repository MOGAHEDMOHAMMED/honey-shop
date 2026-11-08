<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesLine extends Model
{
    use HasUuids;

    protected $table = 'sales_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'entered_quantity',
        'input_unit',
        'conversion_factor',
        'unit_price',
        'discount_rate',
        'discount_amount',
    ];

    protected function casts(): array
    {
        return [
            'entered_quantity' => 'decimal:3',
            'conversion_factor' => 'decimal:6',
            'base_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount_rate' => 'decimal:6',
            'discount_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class, 'transaction_id', 'transaction_id');
    }
}
