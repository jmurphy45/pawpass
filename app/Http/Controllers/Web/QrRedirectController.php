<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\QrCode;
use Illuminate\Http\RedirectResponse;

class QrRedirectController extends Controller
{
    public function redirect(string $token): RedirectResponse
    {
        $qr = QrCode::where('token', $token)
            ->where('is_active', true)
            ->with('tenant')
            ->first();

        abort_if(! $qr || ! $qr->tenant, 404);

        $qr->increment('scan_count');

        $dest = str_starts_with($qr->target_url, 'http')
            ? $qr->target_url
            : 'https://'.$qr->tenant->slug.'.'.config('app.domain').$qr->target_url;

        return redirect($dest, 302);
    }
}
