<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditDetail extends Model
{
    use HasFactory;
    protected $table = 'credit_details';

    protected $fillable = [
        'sale_id',
        'amount',
        'payment_date',
        'detail',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
