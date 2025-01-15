<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointOfSale extends Model
{
    use HasFactory;

    protected $table = 'point_of_sales';
    protected $fillable = ['identifier', 'ubication'];
}
