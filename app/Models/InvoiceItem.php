<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'ingredient_name',
        'normalized_name',
        'price',
        'quantity',
        'unit',
        'divider',
        'price_per_kg',
        'existing_ingredient_id',
        'similarity_score',
        'is_new',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'divider' => 'decimal:2',
        'price_per_kg' => 'decimal:2',
        'similarity_score' => 'integer',
        'is_new' => 'boolean',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function existingIngredient()
    {
        return $this->belongsTo(Ingredient::class, 'existing_ingredient_id');
    }
}