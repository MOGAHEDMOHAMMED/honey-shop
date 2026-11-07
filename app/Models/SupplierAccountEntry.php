<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierAccountEntry extends Model
{
    use HasUuids;

    protected $table = 'supplier_account_entries';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'supplier_id',
        'source_purchase_transaction_id',
        'currency_code',
        'debit',
        'credit',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BusinessTransaction::class, 'transaction_id');
    }
}
