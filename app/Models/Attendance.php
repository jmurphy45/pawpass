<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendance extends Model
{
    use BelongsToTenant, HasFactory, HasUlid;

    protected $fillable = [
        'tenant_id',
        'dog_id',
        'checked_in_by',
        'checked_out_by',
        'checked_in_at',
        'checked_out_at',
        'zero_credit_override',
        'override_note',
        'edited_by',
        'edited_at',
        'edit_note',
        'original_in',
        'original_out',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'immutable_datetime',
            'checked_out_at' => 'immutable_datetime',
            'zero_credit_override' => 'boolean',
            'edited_at' => 'immutable_datetime',
            'original_in' => 'immutable_datetime',
            'original_out' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function dog(): BelongsTo
    {
        return $this->belongsTo(Dog::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function checkedOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_out_by');
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function creditLedger(): HasMany
    {
        return $this->hasMany(CreditLedger::class);
    }
}
