<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'invoice_type',
        'status',
        'total_amount',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'batch_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
