<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VetAppointmentDetail extends Model
{
    use BelongsToTenant, HasFactory, HasUlid;

    protected $fillable = [
        'tenant_id',
        'appointment_id',
        'vet_user_id',
        'resource_id',
        'reason',
        'diagnosis',
        'price_cents',
        'duration_mins',
        'pims_appt_id',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'duration_mins' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function vetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vet_user_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(BookableResource::class, 'resource_id');
    }
}
