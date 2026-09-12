<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseReceipt extends Model
{
    use HasFactory;

    protected $table = 'purchase_receipts';

    protected $fillable = [
        'purchase_order_id',
        'warehouse_id',
        'received_by_user_id',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function receivedBy()
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }
}
