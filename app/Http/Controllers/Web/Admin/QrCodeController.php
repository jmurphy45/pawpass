<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\QrCode;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QrCodeController extends Controller
{
    public function __construct(private readonly QrCodeService $qrCodeService) {}

    public function index(): Response
    {
        $tenantId = app('current.tenant.id');

        $qrCodes = QrCode::all();

        if ($qrCodes->isEmpty()) {
            QrCode::create([
                'tenant_id' => $tenantId,
                'token' => $this->qrCodeService->generateToken(),
                'key' => 'portal',
                'target_url' => '/my',
                'label' => 'Customer Portal',
            ]);
            $qrCodes = QrCode::all();
        }

        $qrCodes = $qrCodes->map(fn (QrCode $qr) => [
            'id' => $qr->id,
            'key' => $qr->key,
            'label' => $qr->label,
            'target_url' => $qr->target_url,
            'is_active' => $qr->is_active,
            'scan_count' => $qr->scan_count,
            'stable_url' => $this->qrCodeService->stableUrl($qr->token),
        ]);

        return Inertia::render('Admin/QrCodes/Index', [
            'qrCodes' => $qrCodes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requireOwner();

        $data = $request->validate([
            'key' => ['required', 'string', 'max:100', 'alpha_dash'],
            'target_url' => ['required', 'string', 'max:2048'],
            'label' => ['nullable', 'string', 'max:255'],
        ]);

        QrCode::create([
            'tenant_id' => app('current.tenant.id'),
            'token' => $this->qrCodeService->generateToken(),
            'key' => $data['key'],
            'target_url' => $data['target_url'],
            'label' => $data['label'] ?? null,
        ]);

        return redirect()->route('admin.qr-codes.index');
    }

    public function update(QrCode $qrCode, Request $request): RedirectResponse
    {
        $this->requireOwner();

        $data = $request->validate([
            'target_url' => ['sometimes', 'required', 'string', 'max:2048'],
            'label' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $qrCode->update($data);

        return redirect()->route('admin.qr-codes.index');
    }

    public function destroy(QrCode $qrCode): RedirectResponse
    {
        $this->requireOwner();

        $qrCode->update(['is_active' => false]);

        return redirect()->route('admin.qr-codes.index');
    }

    public function image(QrCode $qrCode): JsonResponse
    {
        $stableUrl = $this->qrCodeService->stableUrl($qrCode->token);
        $svg = $this->qrCodeService->svg($stableUrl);

        return response()->json([
            'data' => [
                'svg' => 'data:image/svg+xml;base64,'.base64_encode($svg),
                'stable_url' => $stableUrl,
            ],
        ]);
    }

    public function download(QrCode $qrCode): StreamedResponse
    {
        $stableUrl = $this->qrCodeService->stableUrl($qrCode->token);
        $png = $this->qrCodeService->png($stableUrl);
        $filename = str($qrCode->label ?? $qrCode->key)->slug('-').'-qr.png';

        return response()->streamDownload(
            fn () => print ($png),
            $filename,
            ['Content-Type' => 'image/png'],
        );
    }

    private function requireOwner(): void
    {
        if (auth()->user()?->role !== 'business_owner') {
            abort(403, 'Only business owners can manage QR codes.');
        }
    }
}
