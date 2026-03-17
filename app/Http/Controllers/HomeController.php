<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    public function __invoke(Request $request)
    {
        //check if on a tenant or the main domain base off domain
        $isTenant = config('app.tenant_id') !== null;
        if ($isTenant) {
            Log::info('HomeController invoked on tenant', ['tenant_id' => config('app.tenant_id')]);
        } else {
            Log::info('HomeController invoked on main domain', []);
        }
        return inertia('Home');
    }
}