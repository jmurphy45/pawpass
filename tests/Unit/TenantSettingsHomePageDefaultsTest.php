<?php

namespace Tests\Unit;

use App\Models\TenantSettings;
use Tests\TestCase;

class TenantSettingsHomePageDefaultsTest extends TestCase
{
    public function test_home_page_defaults_returns_all_required_keys(): void
    {
        $defaults = TenantSettings::homePageDefaults();

        $this->assertArrayHasKey('hero_headline', $defaults);
        $this->assertArrayHasKey('trust_badges', $defaults);
        $this->assertArrayHasKey('why_section_headline', $defaults);
        $this->assertArrayHasKey('why_cards', $defaults);
        $this->assertArrayHasKey('footer_cta_headline', $defaults);
    }

    public function test_home_page_defaults_trust_badges_has_six_slots(): void
    {
        $defaults = TenantSettings::homePageDefaults();

        $this->assertCount(6, $defaults['trust_badges']);
    }

    public function test_home_page_defaults_trust_badges_first_four_enabled(): void
    {
        $defaults = TenantSettings::homePageDefaults();

        $this->assertTrue($defaults['trust_badges'][0]['enabled']);
        $this->assertTrue($defaults['trust_badges'][1]['enabled']);
        $this->assertTrue($defaults['trust_badges'][2]['enabled']);
        $this->assertTrue($defaults['trust_badges'][3]['enabled']);
        $this->assertFalse($defaults['trust_badges'][4]['enabled']);
        $this->assertFalse($defaults['trust_badges'][5]['enabled']);
    }

    public function test_home_page_defaults_why_cards_has_three_items(): void
    {
        $defaults = TenantSettings::homePageDefaults();

        $this->assertCount(3, $defaults['why_cards']);
    }

    public function test_home_page_defaults_why_cards_have_required_keys(): void
    {
        $defaults = TenantSettings::homePageDefaults();

        foreach ($defaults['why_cards'] as $card) {
            $this->assertArrayHasKey('enabled', $card);
            $this->assertArrayHasKey('icon', $card);
            $this->assertArrayHasKey('title', $card);
            $this->assertArrayHasKey('description', $card);
        }
    }

    public function test_home_page_defaults_hero_headline_is_correct(): void
    {
        $defaults = TenantSettings::homePageDefaults();

        $this->assertEquals("Your dog's home away from home.", $defaults['hero_headline']);
    }

    public function test_home_page_defaults_footer_cta_headline_is_correct(): void
    {
        $defaults = TenantSettings::homePageDefaults();

        $this->assertEquals('Ready to join the pack?', $defaults['footer_cta_headline']);
    }
}
