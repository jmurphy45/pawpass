<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSettings extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_settings';

    protected $fillable = [
        'tenant_id',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public static function homePageDefaults(): array
    {
        return [
            'hero_headline' => "Your dog's home away from home.",
            'trust_badges' => [
                ['text' => 'Licensed & Insured',  'enabled' => true],
                ['text' => 'Loving Care',          'enabled' => true],
                ['text' => 'Locally Owned',        'enabled' => true],
                ['text' => 'Open 7 Days a Week',   'enabled' => true],
                ['text' => '',                     'enabled' => false],
                ['text' => '',                     'enabled' => false],
            ],
            'why_section_headline' => "The difference is\nin the care.",
            'why_cards' => [
                [
                    'enabled' => true,
                    'icon' => 'shield',
                    'title' => 'Safe & Supervised',
                    'description' => 'Every dog is supervised by trained, caring staff throughout the entire day. Safety is always our first priority.',
                ],
                [
                    'enabled' => true,
                    'icon' => 'heart',
                    'title' => 'Enriching Playtime',
                    'description' => 'Dogs are social creatures. Structured play, walks, and enrichment activities keep tails wagging all day long.',
                ],
                [
                    'enabled' => true,
                    'icon' => 'phone',
                    'title' => 'Easy Online Booking',
                    'description' => "Manage your dog's schedule, purchase credits, and track visit history — all from your phone or computer.",
                ],
            ],
            'footer_cta_headline' => 'Ready to join the pack?',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
