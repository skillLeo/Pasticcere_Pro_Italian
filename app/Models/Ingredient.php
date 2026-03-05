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
        'user_id', // ✅ allow mass assignment of user_id
         'recipe_id'
,
         'last_invoice_date',
'last_invoice_code',
    ];
    protected $casts = [
        'price_per_kg'     => 'decimal:4',
        'additional_names' => 'array',
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
