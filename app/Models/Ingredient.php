<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\RecipeIngredient;
use App\Models\User;

class Ingredient extends Model
{
    use HasFactory;

    protected $fillable = [
        'ingredient_name',
        'additional_names',
        'price_per_kg',
        'user_id',
        'recipe_id',
        'last_invoice_date',
        'last_invoice_code',
    ];

    protected $casts = [
        /*
         * ╔════════════════════════════════════════════════════════════════╗
         * ║  price_per_kg cast — WHY 'decimal:4' NOT 'float'               ║
         * ║                                                                  ║
         * ║  'float' cast  → PHP reads the DB value as a native float.      ║
         * ║    When you then call number_format() or echo it, if the server ║
         * ║    LC_NUMERIC locale is set to Italian (it_IT), PHP prints       ║
         * ║    25.0 as "25,0000" — comma as decimal separator.              ║
         * ║                                                                  ║
         * ║  'decimal:4' cast → Laravel reads the DB value and ALWAYS       ║
         * ║    returns it as a string formatted with DOT decimal: "25.0000" ║
         * ║    This bypasses PHP's locale completely for read operations.    ║
         * ║                                                                  ║
         * ║  For arithmetic (e.g. cascade cost calculations), cast the      ║
         * ║    attribute to float explicitly: (float) $ingredient->price_per_kg ║
         * ╚════════════════════════════════════════════════════════════════╝
         */
        'price_per_kg'     => 'decimal:4',  // always "25.0000" — never "25,0000"
        'additional_names' => 'array',
        'last_invoice_date' => 'date',
    ];

    public function ingredientRecipes()
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function recipe()
    {
        return $this->belongsTo(Recipe::class, 'recipe_id');
    }
}