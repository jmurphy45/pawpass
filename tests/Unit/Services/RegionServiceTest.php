<?php

namespace Tests\Unit\Services;

use App\Services\RegionService;
use Tests\TestCase;

class RegionServiceTest extends TestCase
{
    private RegionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RegionService;
    }

    public function test_us_states_returns_value_label_pairs(): void
    {
        $states = $this->service->usStates();

        $this->assertNotEmpty($states);
        $this->assertArrayHasKey('value', $states[0]);
        $this->assertArrayHasKey('label', $states[0]);
    }

    public function test_us_states_values_are_abbreviations_not_full_codes(): void
    {
        $states = $this->service->usStates();

        foreach ($states as $state) {
            $this->assertStringNotContainsString('-', $state['value'], "State value '{$state['value']}' should not contain a dash");
            $this->assertSame(2, strlen($state['value']), "State value '{$state['value']}' should be 2 chars");
        }
    }

    public function test_us_states_are_sorted_by_name(): void
    {
        $states = $this->service->usStates();
        $names = array_column($states, 'label');

        $this->assertSame('Alabama', $names[0]);
        $this->assertContains('Texas', $names);
        $this->assertLessThan(
            array_search('Texas', $names),
            array_search('Alabama', $names)
        );
    }

    public function test_us_states_are_uppercase(): void
    {
        $states = $this->service->usStates();

        foreach ($states as $state) {
            $this->assertSame(strtoupper($state['value']), $state['value']);
        }
    }

    public function test_for_country_ca_returns_provinces(): void
    {
        $provinces = $this->service->forCountry('CA');

        $this->assertNotEmpty($provinces);
        $values = array_column($provinces, 'value');
        $this->assertContains('BC', $values);
        $this->assertContains('ON', $values);
    }

    public function test_for_country_gb_returns_empty_array(): void
    {
        $this->assertSame([], $this->service->forCountry('GB'));
    }

    public function test_for_country_au_returns_empty_array(): void
    {
        $this->assertSame([], $this->service->forCountry('AU'));
    }
}
