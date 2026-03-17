<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformConfig extends Model
{
    public const CREATED_AT = null;

    protected $table = 'platform_config';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $record = static::find($key);

        return $record ? $record->value : $default;
    }
}
