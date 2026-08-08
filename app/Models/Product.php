<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'image',
        'category_id',
        'quantity',
        'batch_id',
        'final_cost',
        'wholesale_final_cost',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class); // Establece la relación con el modelo Product
    }

    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }

    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class)->withPivot('quantity')->withTimestamps();
    }

    public function batches()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }
}
