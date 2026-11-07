<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningInventoryLine extends Model
{
    protected $table = 'opening_inventory_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'product_id',
        'quantity',
        'entered_quantity',
        'input_unit',
        'conversion_factor',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'entered_quantity' => 'decimal:3',
            'conversion_factor' => 'decimal:6',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function openingInventory(): BelongsTo
    {
        return $this->belongsTo(OpeningInventory::class, 'transaction_id', 'transaction_id');
    }
}
