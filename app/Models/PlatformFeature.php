<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlatformFeature extends Model
{
    use HasFactory, HasUlid;

    protected $table = 'platform_features';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_marketing',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_marketing' => 'boolean',
            'sort_order'   => 'integer',
        ];
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(PlatformPlan::class, 'platform_plan_features', 'feature_id', 'plan_id');
    }
}
