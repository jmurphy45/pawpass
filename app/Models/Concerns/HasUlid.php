<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasUlid
{
    public static function bootHasUlid(): void
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                do {
                    $id = (string) Str::ulid();
                } while ($model::withoutGlobalScopes()->where($model->getKeyName(), $id)->exists());
                $model->{$model->getKeyName()} = $id;
            }
        });
    }

    public function initializeHasUlid(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    public function uniqueIds(): array
    {
        return [$this->getKeyName()];
    }

    public function newUniqueId(): string
    {
        return (string) Str::ulid();
    }
}
