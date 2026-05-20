<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DaycareBookingDetail extends Model
{
    use BelongsToTenant, HasFactory, HasUlid;

    protected $fillable = [
        'tenant_id',
        'appointment_id',
        'attendance_id',
        'credit_hold_ledger_id',
        'credit_deducted_at',
        'drop_off_window_start',
        'drop_off_window_end',
    ];

    protected function casts(): array
    {
        return [
            'credit_deducted_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    public function creditHoldLedger(): BelongsTo
    {
        return $this->belongsTo(CreditLedger::class, 'credit_hold_ledger_id');
    }
}
