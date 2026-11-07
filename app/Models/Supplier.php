<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasUuids;

    protected $table = 'suppliers';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'supplier_type_id',
        'name',
        'phone',
        'address',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(SupplierType::class, 'supplier_type_id');
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(PurchaseInvoice::class, 'supplier_id', 'id');
    }

    public function accountEntries(): HasMany
    {
        return $this->hasMany(SupplierAccountEntry::class, 'supplier_id', 'id');
    }
}
