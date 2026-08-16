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
        return $this->belongsTo(Category::class);
    }

    /**
     * Legacy compatibility relation used by the current frontend.
     *
     * A zero-balance inventory row represents an inactive assignment and
     * must not make the product appear assigned to a warehouse.
     *
     * New Phase 2 code should prefer inventories().
     */
    public function inventory()
    {
        return $this->hasOne(Inventory::class)
            ->where('quantity', '>', 0);
    }

    /**
     * Canonical Phase 2 relation: a product may have one balance
     * per warehouse.
     */
    public function inventories()
    {
        return $this->hasMany(Inventory::class);
    }

    public function warehouses()
    {
        return $this->belongsToMany(Warehouse::class)
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function batches()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }
}
