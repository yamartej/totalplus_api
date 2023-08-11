<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    use HasFactory;

    protected $table = 'warehouses';

    protected $fillable = ['name', 'description', 'address'];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_warehouse')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }

    public function cashRegisters()
    {
        return $this->hasMany(CashRegister::class);
    }
}
