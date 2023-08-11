<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $table = 'purchase_orders';

    protected $fillable = ['supplier_id', 'tracking_number', 'shipping_cost'];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
