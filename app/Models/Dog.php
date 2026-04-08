<?php

namespace App\Models;

use App\Enums\DogStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use App\Models\Package;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dog extends Model
{
    use BelongsToTenant, HasFactory, HasUlid, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'name',
        'breed',
        'dob',
        'sex',
        'photo_url',
        'vet_name',
        'vet_phone',
        'credit_balance',
        'credits_expire_at',
        'unlimited_pass_expires_at',
        'credits_alert_sent_at',
        'auto_replenish_enabled',
        'auto_replenish_package_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'credit_balance' => 'integer',
            'credits_expire_at' => 'immutable_datetime',
            'unlimited_pass_expires_at' => 'immutable_datetime',
            'credits_alert_sent_at' => 'immutable_datetime',
            'auto_replenish_enabled' => 'boolean',
            'status' => DogStatus::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function autoReplenishPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'auto_replenish_package_id');
    }

    public function creditLedger(): HasMany
    {
        return $this->hasMany(CreditLedger::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function vaccinations(): HasMany
    {
        return $this->hasMany(DogVaccination::class);
    }

    public function autoReplenishConfigured(): bool
    {
        return $this->auto_replenish_enabled && ! empty($this->auto_replenish_package_id);
    }
}
