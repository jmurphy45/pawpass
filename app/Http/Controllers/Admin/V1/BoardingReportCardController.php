<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBoardingReportCardRequest;
use App\Http\Requests\Admin\UpdateBoardingReportCardRequest;
use App\Http\Resources\BoardingReportCardResource;
use App\Models\BoardingReportCard;
use App\Models\Reservation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BoardingReportCardController extends Controller
{
    public function index(Reservation $reservation): AnonymousResourceCollection
    {
        $cards = $reservation->reportCards()->orderBy('report_date')->get();

        return BoardingReportCardResource::collection($cards);
    }

    public function store(StoreBoardingReportCardRequest $request, Reservation $reservation): JsonResponse
    {
        try {
            $card = BoardingReportCard::updateOrCreate(
                [
                    'reservation_id' => $reservation->id,
                    'report_date'    => $request->report_date,
                ],
                [
                    'tenant_id'  => app('current.tenant.id'),
                    'notes'      => $request->notes,
                    'created_by' => auth()->id(),
                ]
            );
        } catch (UniqueConstraintViolationException) {
            // Race condition fallback — retry as update
            $card = BoardingReportCard::where('reservation_id', $reservation->id)
                ->where('report_date', $request->report_date)
                ->firstOrFail();
            $card->update(['notes' => $request->notes]);
        }

        $status = $card->wasRecentlyCreated ? 201 : 200;

        return response()->json(['data' => new BoardingReportCardResource($card->fresh())], $status);
    }

    public function update(UpdateBoardingReportCardRequest $request, Reservation $reservation, BoardingReportCard $reportCard): JsonResponse
    {
        if ($reportCard->reservation_id !== $reservation->id) {
            abort(404);
        }

        $reportCard->update(['notes' => $request->notes]);

        return response()->json(['data' => new BoardingReportCardResource($reportCard->fresh())]);
    }
}
