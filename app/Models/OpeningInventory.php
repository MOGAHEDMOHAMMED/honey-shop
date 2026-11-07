<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpeningInventory extends Model
{
    protected $table = 'opening_inventory';

    protected $primaryKey = 'transaction_id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'effective_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'immutable_datetime',
        ];
    }

    public function businessTransaction(): BelongsTo
    {
        return $this->belongsTo(BusinessTransaction::class, 'transaction_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(OpeningInventoryLine::class, 'transaction_id', 'transaction_id');
    }
}
