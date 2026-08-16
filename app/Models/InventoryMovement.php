<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class InventoryMovement extends Model
{
    use HasFactory;

    public const TYPE_OPENING = 'opening';
    public const TYPE_RECEIVE = 'receive';
    public const TYPE_ISSUE = 'issue';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_TRANSFER_IN = 'transfer_in';
    public const TYPE_TRANSFER_OUT = 'transfer_out';

    protected $fillable = [
        'company_id',
        'warehouse_id',
        'product_id',
        'type',
        'quantity_delta',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'warehouse_id' => 'integer',
        'product_id' => 'integer',
        'quantity_delta' => 'integer',
        'balance_before' => 'integer',
        'balance_after' => 'integer',
        'reference_id' => 'integer',
        'user_id' => 'integer',
    ];

    protected static function booted()
    {
        static::updating(function () {
            throw new LogicException(
                'Inventory movements are immutable and cannot be updated.'
            );
        });

        static::deleting(function () {
            throw new LogicException(
                'Inventory movements are immutable and cannot be deleted.'
            );
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}