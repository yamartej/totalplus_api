<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleDetail extends Model
{
    use HasFactory;

    protected $table = 'sales_details';

    protected $fillable = [
        'sale_id',
        'product_id',
        'warehouse_id',
        'quantity',
        'unit_price',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
