<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\BookableResource;
use App\Models\Customer;
use App\Models\Dog;
use App\Services\GroomingAppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroomingAppointmentController extends Controller
{
    public function __construct(private readonly GroomingAppointmentService $service) {}

    public function index(Request $request): Response
    {
        $query = Appointment::ofType('grooming')
            ->with(['dog:id,name', 'customer:id,name', 'groomingDetail'])
            ->orderBy('starts_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $query->whereDate('starts_at', $request->date);
        }

        $appointments = $query->paginate(25)->withQueryString();

        $dogs = Dog::select('id', 'name', 'customer_id')
            ->with('customer:id,name')
            ->orderBy('name')
            ->get();

        $resources = BookableResource::where('is_active', true)
            ->whereIn('resource_type', ['grooming_bay'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'resource_type', 'capacity']);

        $customers = Customer::orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Grooming/Index', [
            'appointments' => $appointments,
            'dogs' => $dogs,
            'resources' => $resources,
            'customers' => $customers,
            'filters' => $request->only(['status', 'date']),
        ]);
    }

    public function show(Appointment $appointment): Response
    {
        abort_if($appointment->service_type !== 'grooming', 404);

        $appointment->load(['groomingDetail.resource', 'dog', 'customer', 'order.payments']);

        return Inertia::render('Admin/Grooming/Show', [
            'appointment' => $appointment,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'dog_id' => ['required', 'string', 'exists:dogs,id'],
            'customer_id' => ['required', 'string', 'exists:customers,id'],
            'service_name' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'duration_mins' => ['nullable', 'integer', 'min:1'],
            'resource_id' => ['nullable', 'string', 'exists:bookable_resources,id'],
            'groomer_user_id' => ['nullable', 'string', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $appointment = $this->service->create(array_merge($validated, [
                'tenant_id' => app('current.tenant.id'),
            ]));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['resource_id' => 'That grooming bay is already booked for this time.']);
        }

        return redirect()->route('admin.grooming.appointments.show', ['appointment' => $appointment->id])
            ->with('success', 'Grooming appointment created.');
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_if($appointment->service_type !== 'grooming', 404);

        $validated = $request->validate([
            'service_name' => ['sometimes', 'string'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['nullable', 'date'],
            'price_cents' => ['sometimes', 'integer', 'min:0'],
            'duration_mins' => ['nullable', 'integer', 'min:1'],
            'resource_id' => ['nullable', 'string', 'exists:bookable_resources,id'],
            'groomer_user_id' => ['nullable', 'string', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $this->service->update($appointment, $validated);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['resource_id' => 'That grooming bay is already booked for this time.']);
        }

        return back()->with('success', 'Appointment updated.');
    }

    public function confirm(Appointment $appointment): RedirectResponse
    {
        abort_if($appointment->service_type !== 'grooming', 404);
        $appointment->transitionTo('confirmed');

        return back()->with('success', 'Appointment confirmed.');
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_if($appointment->service_type !== 'grooming', 404);

        $request->validate(['cancellation_reason' => ['nullable', 'string']]);

        $appointment->update(['cancellation_reason' => $request->cancellation_reason]);
        $appointment->transitionTo('cancelled', auth()->id());

        return back()->with('success', 'Appointment cancelled.');
    }
}
