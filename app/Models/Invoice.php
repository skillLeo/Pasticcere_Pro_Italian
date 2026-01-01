<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'batch_id',         // ✅ ADD THIS
        'file_path',
        'file_type',
        'raw_text',
        'invoice_type',
        'status',
        'parsed',
        'total_amount',
    ];

    protected $casts = [
        'parsed' => 'boolean',
        'total_amount' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function batch()
    {
        return $this->belongsTo(InvoiceBatch::class, 'batch_id');
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }
}
