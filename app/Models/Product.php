<?php

namespace App\Models;

use App\Enums\BaseInventoryUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasUuids;

    protected $table = 'products';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'sku',
        'name',
        'category_id',
        'base_unit',
        'minimum_stock',
        'is_active',
        'notes',
        'selling_price',
        'max_discount_rate',
    ];

    protected function casts(): array
    {
        return [
            'base_unit' => BaseInventoryUnit::class,
            'minimum_stock' => 'decimal:3',
            'selling_price' => 'decimal:4',
            'max_discount_rate' => 'decimal:6',
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function inventoryBalance(): HasOne
    {
        return $this->hasOne(InventoryBalance::class, 'product_id');
    }
}
