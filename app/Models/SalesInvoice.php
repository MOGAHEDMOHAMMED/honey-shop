<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesInvoice extends Model
{
    protected $table = 'sales_invoices';

    protected $primaryKey = 'transaction_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'invoice_number',
        'series_category',
        'customer_id',
        'sales_channel',
        'currency_code',
        'representative_id',
        'delivery_provider_id',
        'order_status',
        'products_subtotal',
        'discounts_total',
        'delivery_charge',
        'final_total',
        'delivery_cost_bearer',
    ];

    protected function casts(): array
    {
        return [
            'products_subtotal' => 'decimal:2',
            'discounts_total' => 'decimal:2',
            'delivery_charge' => 'decimal:2',
            'final_total' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function businessTransaction(): BelongsTo
    {
        return $this->belongsTo(BusinessTransaction::class, 'transaction_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesLine::class, 'transaction_id', 'transaction_id');
    }
}
