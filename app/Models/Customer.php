<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['client_id', 'name', 'address', 'phone'];

    public function sale()
    {
        return $this->hasMany(Sale::class);
    }

    public function creditCustomerDetails()
    {
        return $this->hasOne(CreditCustomerDetail::class);
    }
}
