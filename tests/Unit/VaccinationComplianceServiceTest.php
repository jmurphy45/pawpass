<?php

namespace Tests\Unit;

use App\Models\Dog;
use App\Models\DogVaccination;
use App\Models\Tenant;
use App\Models\VaccinationRequirement;
use App\Services\VaccinationComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VaccinationComplianceServiceTest extends TestCase
{
    use RefreshDatabase;

    private VaccinationComplianceService $service;

    private Tenant $tenant;

    private Dog $dog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VaccinationComplianceService;
        $this->tenant = Tenant::factory()->create();
        $this->dog = Dog::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_compliant_when_no_requirements_configured(): void
    {
        // No VaccinationRequirements for this tenant
        $this->assertTrue($this->service->isCompliant($this->dog, $this->tenant->id));
        $this->assertEmpty($this->service->getViolations($this->dog, $this->tenant->id));
    }

    public function test_compliant_when_dog_has_all_required_valid_vaccines(): void
    {
        VaccinationRequirement::factory()->create(['tenant_id' => $this->tenant->id, 'vaccine_name' => 'Rabies']);
        VaccinationRequirement::factory()->create(['tenant_id' => $this->tenant->id, 'vaccine_name' => 'Bordetella']);

        DogVaccination::factory()->create(['tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id, 'vaccine_name' => 'Rabies', 'expires_at' => now()->addYear()->toDateString()]);
        DogVaccination::factory()->create(['tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id, 'vaccine_name' => 'Bordetella', 'expires_at' => now()->addYear()->toDateString()]);

        $this->assertTrue($this->service->isCompliant($this->dog, $this->tenant->id));
    }

    public function test_violation_when_required_vaccine_is_missing(): void
    {
        VaccinationRequirement::factory()->create(['tenant_id' => $this->tenant->id, 'vaccine_name' => 'Rabies']);

        $violations = $this->service->getViolations($this->dog, $this->tenant->id);

        $this->assertContains('rabies', $violations);
        $this->assertFalse($this->service->isCompliant($this->dog, $this->tenant->id));
    }

    public function test_violation_when_required_vaccine_is_expired(): void
    {
        VaccinationRequirement::factory()->create(['tenant_id' => $this->tenant->id, 'vaccine_name' => 'Rabies']);
        DogVaccination::factory()->expired()->create(['tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id, 'vaccine_name' => 'Rabies']);

        $violations = $this->service->getViolations($this->dog, $this->tenant->id);

        $this->assertContains('rabies', $violations);
    }

    public function test_no_expiry_vaccine_is_always_valid(): void
    {
        VaccinationRequirement::factory()->create(['tenant_id' => $this->tenant->id, 'vaccine_name' => 'DHPP']);
        DogVaccination::factory()->noExpiry()->create(['tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id, 'vaccine_name' => 'DHPP']);

        $this->assertTrue($this->service->isCompliant($this->dog, $this->tenant->id));
    }

    public function test_multiple_violations_returned(): void
    {
        VaccinationRequirement::factory()->create(['tenant_id' => $this->tenant->id, 'vaccine_name' => 'Rabies']);
        VaccinationRequirement::factory()->create(['tenant_id' => $this->tenant->id, 'vaccine_name' => 'Bordetella']);
        VaccinationRequirement::factory()->create(['tenant_id' => $this->tenant->id, 'vaccine_name' => 'DHPP']);

        // Only give the dog one valid vaccine
        DogVaccination::factory()->create(['tenant_id' => $this->tenant->id, 'dog_id' => $this->dog->id, 'vaccine_name' => 'Rabies', 'expires_at' => now()->addYear()->toDateString()]);

        $violations = $this->service->getViolations($this->dog, $this->tenant->id);

        $this->assertCount(2, $violations);
        $this->assertContains('bordetella', $violations);
        $this->assertContains('dhpp', $violations);
    }
}
