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

    public function costs()
    {
        return $this->hasMany(Cost::class);
    }

    public function products()
    {
        return $this->hasMany(
            Product::class,
            'batch_id'
        );
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
