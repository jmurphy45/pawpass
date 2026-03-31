<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LogoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if (auth()->user()?->role !== 'business_owner') {
            abort(403, 'Only business owners can manage the logo.');
        }

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
        ]);

        $tenant = Tenant::find(app('current.tenant.id'));

        $this->deleteExistingLogo($tenant);

        $ext = $request->file('logo')->getClientOriginalExtension();
        $path = Storage::disk('s3')->putFileAs(
            "tenants/{$tenant->id}",
            $request->file('logo'),
            "logo.{$ext}",
            'public'
        );

        $tenant->update(['logo_url' => Storage::disk('s3')->url($path)]);

        return back()->with('success', 'Logo updated.');
    }

    public function destroy(): RedirectResponse
    {
        if (auth()->user()?->role !== 'business_owner') {
            abort(403, 'Only business owners can manage the logo.');
        }

        $tenant = Tenant::find(app('current.tenant.id'));

        $this->deleteExistingLogo($tenant);

        $tenant->update(['logo_url' => null]);

        return back()->with('success', 'Logo removed.');
    }

    private function deleteExistingLogo(Tenant $tenant): void
    {
        if ($tenant->logo_url === null) {
            return;
        }

        $files = Storage::disk('s3')->files("tenants/{$tenant->id}");

        foreach ($files as $file) {
            if (str_starts_with(basename($file), 'logo.')) {
                Storage::disk('s3')->delete($file);
            }
        }
    }
}
