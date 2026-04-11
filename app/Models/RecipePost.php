<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipePost extends Model
{
    protected $fillable = [
        'user_id',
        'source',
        'ext_id',
        'title',
        'title_en',
        'category',
        'cook_method',
        'ingredients',
        'ingredients_en',
        'ingredients_structured',
        'servings',
        'calories',
        'carbs',
        'protein',
        'fat',
        'sodium',
        'steps',
        'thumbnail',
        'hash_tags',
        'view_count',
        'like_count',
        'rating_avg',
        'rating_count',
        'favorite_count',
        'is_active',
        'translated_at',
    ];

    protected $casts = [
        'steps' => 'array',
        'ingredients_structured' => 'array',
        'is_active' => 'boolean',
        'rating_avg' => 'decimal:2',
        'translated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function favorites()
    {
        return $this->hasMany(RecipeFavorite::class, 'recipe_id');
    }

    public function ratings()
    {
        return $this->hasMany(RecipeRating::class, 'recipe_id');
    }

    public function recomputeRating(): void
    {
        $agg = $this->ratings()->selectRaw('AVG(rating) as avg, COUNT(*) as cnt')->first();
        $this->update([
            'rating_avg' => round((float) ($agg->avg ?? 0), 2),
            'rating_count' => (int) ($agg->cnt ?? 0),
        ]);
    }

    public function recomputeFavoriteCount(): void
    {
        $this->update(['favorite_count' => $this->favorites()->count()]);
    }
}
