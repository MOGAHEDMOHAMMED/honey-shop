<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceHistory extends Model
{
    protected $table = 'product_price_history';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'previous_selling_price',
        'new_selling_price',
        'previous_max_discount_rate',
        'new_max_discount_rate',
        'actor_user_id',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_selling_price' => 'decimal:2',
            'new_selling_price' => 'decimal:2',
            'previous_max_discount_rate' => 'decimal:6',
            'new_max_discount_rate' => 'decimal:6',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
