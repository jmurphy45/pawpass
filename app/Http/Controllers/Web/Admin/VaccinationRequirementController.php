<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\VaccinationRequirement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class VaccinationRequirementController extends Controller
{
    public function index(): Response
    {
        $requirements = VaccinationRequirement::orderBy('vaccine_name')->get(['id', 'vaccine_name']);

        return Inertia::render('Admin/VaccinationRequirements/Index', [
            'requirements' => $requirements,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()?->role === 'business_owner', 403);

        $validated = $request->validate([
            'vaccine_name' => ['required', 'string', 'max:255'],
        ]);

        VaccinationRequirement::firstOrCreate([
            'tenant_id'    => app('current.tenant.id'),
            'vaccine_name' => $validated['vaccine_name'],
        ]);

        return redirect()->route('admin.vaccination-requirements.index')
            ->with('success', 'Vaccination requirement added.');
    }

    public function destroy(VaccinationRequirement $vaccinationRequirement): RedirectResponse
    {
        abort_unless(Auth::user()?->role === 'business_owner', 403);

        $vaccinationRequirement->delete();

        return redirect()->route('admin.vaccination-requirements.index')
            ->with('success', 'Vaccination requirement removed.');
    }
}
