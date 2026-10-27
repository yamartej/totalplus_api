<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseReceiptLine extends Model
{
    protected $table = 'purchase_receipt_lines';

    protected $fillable = [
        'purchase_receipt_id',
        'purchase_order_product_id',
        'product_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function receipt()
    {
        return $this->belongsTo(PurchaseReceipt::class, 'purchase_receipt_id');
    }

    public function orderLine()
    {
        return $this->belongsTo(PurchaseOrderProduct::class, 'purchase_order_product_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
