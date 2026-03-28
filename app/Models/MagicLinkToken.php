<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MagicLinkToken extends Model
{
    use SoftDeletes;

    protected $table = 'magic_link_tokens';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'token_hash',
        'fp_hash',
        'fp_components',
        'ip_address',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'fp_components' => 'array',
            'created_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    // No auto-managed updated_at
    const UPDATED_AT = null;

    /**
     * Boot the model and auto-generate UUID on create.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    /**
     * @return BelongsTo<User, MagicLinkToken>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to tokens that are still valid (not expired, not used, not soft-deleted).
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query
            ->whereNull('used_at')
            ->whereNull('deleted_at')
            ->where('expires_at', '>', now());
    }
}
