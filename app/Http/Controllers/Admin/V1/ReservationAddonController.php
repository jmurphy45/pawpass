<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreReservationAddonRequest;
use App\Http\Resources\ReservationAddonResource;
use App\Models\AddonType;
use App\Models\Reservation;
use App\Models\ReservationAddon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReservationAddonController extends Controller
{
    public function index(Reservation $reservation): AnonymousResourceCollection
    {
        return ReservationAddonResource::collection(
            $reservation->addons()->with('addonType')->get()
        );
    }

    public function store(StoreReservationAddonRequest $request, Reservation $reservation): JsonResponse
    {
        if ($reservation->isCancelled()) {
            return response()->json(['error' => 'RESERVATION_CANCELLED'], 409);
        }

        $addonType = AddonType::find($request->addon_type_id);

        if (! $addonType || ! $addonType->appliesToBoarding()) {
            abort(404);
        }

        $addon = $reservation->addons()->create([
            'addon_type_id'    => $addonType->id,
            'quantity'         => $request->quantity ?? 1,
            'unit_price_cents' => $addonType->price_cents,
            'note'             => $request->note,
        ]);

        return response()->json(['data' => new ReservationAddonResource($addon->load('addonType'))], 201);
    }

    public function destroy(Reservation $reservation, ReservationAddon $addon): JsonResponse
    {
        if ($addon->reservation_id !== $reservation->id) {
            abort(404);
        }

        $resource = new ReservationAddonResource($addon);
        $addon->delete();

        return response()->json(['data' => $resource]);
    }
}
