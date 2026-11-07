<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseInvoice extends Model
{
    protected $table = 'purchase_invoices';

    protected $primaryKey = 'transaction_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'invoice_number',
        'supplier_invoice_number',
        'supplier_id',
        'currency_code',
        'invoice_total',
    ];

    protected function casts(): array
    {
        return [
            'invoice_total' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function businessTransaction(): BelongsTo
    {
        return $this->belongsTo(BusinessTransaction::class, 'transaction_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class, 'transaction_id', 'transaction_id');
    }
}
