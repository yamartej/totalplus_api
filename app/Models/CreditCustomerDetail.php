<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditCustomerDetail extends Model
{
    use HasFactory;
    protected $table = 'credit_customer_details';

    protected $fillable = [
        'customer_id',
        'amount',
        'payment_date',
        'detail',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
}
