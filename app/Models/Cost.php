<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cost extends Model
{
    use HasFactory;

    protected $table = 'costs';

    protected $fillable = [
        'amount',
        'description',
        'batch_id',
        'created_at',
        'updated_at'
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}
