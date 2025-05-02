<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use HasFactory;
    protected $table = 'batches';

    protected $fillable = [
        'name',
        'description',
        'quantity',
        'status',
        'order_creation_date',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class)->withPivot('quantity')->withTimestamps();
    }

    public function costs()
    {
        return $this->hasMany(Cost::class);
    }
}
