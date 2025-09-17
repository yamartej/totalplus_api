<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'seller_id',
        'pop_id',
        'total_amount',
        'type_of_sale',
        'credit_note_date',
        'credit_note_detail',
        'company_id',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
    public function seller()
    {
        return $this->belongsTo(User::class);
    }
    public function pop()
    {
        return $this->belongsTo(PointOfSale::class);
    }
    public function details()
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function paymentDetails()
    {
        return $this->hasMany(CreditDetail::class);
    }
}
