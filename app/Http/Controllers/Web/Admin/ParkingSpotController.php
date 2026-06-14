<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ParkingSpot;
use App\Models\QrCode;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParkingSpotController extends Controller
{
    public function __construct(private readonly QrCodeService $qrCodeService) {}

    public function index(): Response
    {
        $parkingSpots = ParkingSpot::with('qrCode')->orderBy('sort_order')->orderBy('spot_number')->get()->map(fn (ParkingSpot $spot) => [
            'id' => $spot->id,
            'spot_number' => $spot->spot_number,
            'name' => $spot->name,
            'description' => $spot->description,
            'location' => $spot->location,
            'is_active' => $spot->is_active,
            'sort_order' => $spot->sort_order,
            'qr_key' => $spot->qr_key,
            'qr_code' => $spot->qrCode ? [
                'id' => $spot->qrCode->id,
                'stable_url' => $this->qrCodeService->stableUrl($spot->qrCode->token),
                'scan_count' => $spot->qrCode->scan_count,
            ] : null,
        ]);

        return Inertia::render('Admin/ParkingSpots/Index', [
            'parkingSpots' => $parkingSpots,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireOwner();

        $data = $request->validate([
            'spot_number' => ['required', 'string', 'max:50', 'alpha_num'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $parkingSpot = ParkingSpot::create($data);

        $this->createQrCodeForParkingSpot($parkingSpot);

        return redirect()->back();
    }

    public function update(Request $request, ParkingSpot $parkingSpot): RedirectResponse
    {
        $this->requireOwner();

        $data = $request->validate([
            'spot_number' => ['required', 'string', 'max:50', 'alpha_num'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ]);

        $oldSpotNumber = $parkingSpot->spot_number;
        $parkingSpot->update($data);

        if ($oldSpotNumber !== $parkingSpot->spot_number) {
            // Find QR code with the old key and update it
            $qrCode = QrCode::where('tenant_id', $parkingSpot->tenant_id)
                ->where('key', "parking-{$oldSpotNumber}")
                ->first();

            if ($qrCode) {
                $qrCode->update([
                    'key' => $parkingSpot->qr_key,
                    'label' => "Parking Spot {$parkingSpot->spot_number}",
                ]);
            }
        }

        return redirect()->back();
    }

    public function destroy(ParkingSpot $parkingSpot): RedirectResponse
    {
        $this->requireOwner();

        $parkingSpot->delete();

        if ($parkingSpot->qrCode) {
            $parkingSpot->qrCode->update(['is_active' => false]);
        }

        return redirect()->back();
    }

    public function qrCodeImage(ParkingSpot $parkingSpot): JsonResponse
    {
        if (! $parkingSpot->qrCode) {
            return response()->json(['error' => 'QR code not found'], 404);
        }

        $stableUrl = $this->qrCodeService->stableUrl($parkingSpot->qrCode->token);
        $svg = $this->qrCodeService->svg($stableUrl, 128);

        return response()->json([
            'data' => [
                'svg' => 'data:image/svg+xml;base64,'.base64_encode($svg),
            ],
        ]);
    }

    public function qrCodeDownload(ParkingSpot $parkingSpot)
    {
        if (! $parkingSpot->qrCode) {
            abort(404);
        }

        $stableUrl = $this->qrCodeService->stableUrl($parkingSpot->qrCode->token);
        $png = $this->qrCodeService->png($stableUrl, 256);

        $filename = sprintf('%s-qr.png', str($parkingSpot->spot_number)->slug());

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    private function createQrCodeForParkingSpot(ParkingSpot $parkingSpot): void
    {
        QrCode::create([
            'tenant_id' => $parkingSpot->tenant_id,
            'token' => $this->qrCodeService->generateToken(),
            'key' => $parkingSpot->qr_key,
            'target_url' => "/my/arrive/{$parkingSpot->tenant_id}/{$parkingSpot->id}",
            'label' => "Parking Spot {$parkingSpot->spot_number}",
            'is_active' => true,
            'scan_count' => 0,
        ]);
    }

    private function requireOwner(): void
    {
        if (auth()->user()?->role !== 'business_owner') {
            abort(403, 'Only business owners can manage parking spots.');
        }
    }
}
