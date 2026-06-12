<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParkingSpot extends Model
{
    use BelongsToTenant, HasFactory, HasUlid, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'spot_number',
        'name',
        'description',
        'location',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function qrCode()
    {
        return $this->hasOne(QrCode::class, 'tenant_id', 'tenant_id')
            ->where('key', $this->qr_key);
    }

    public function getQrKeyAttribute(): string
    {
        return "parking-{$this->spot_number}";
    }
}
