<?php

namespace App\Http\Controllers\Admin\V1;

use App\Http\Controllers\Controller;
use App\Models\DaycareCapacityWindow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DaycareCapacityWindowController extends Controller
{
    public function index(): JsonResponse
    {
        $windows = DaycareCapacityWindow::orderBy('recurrence')->orderBy('day_of_week')->get();

        return response()->json(['data' => $windows]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string'],
            'recurrence' => ['required', 'in:weekly,one_time'],
            'day_of_week' => ['required_if:recurrence,weekly', 'nullable', 'integer', 'min:0', 'max:6'],
            'specific_date' => ['required_if:recurrence,one_time', 'nullable', 'date'],
            'opens_at' => ['required', 'date_format:H:i'],
            'closes_at' => ['required', 'date_format:H:i', 'after:opens_at'],
            'max_dogs' => ['required', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        $window = DaycareCapacityWindow::create($validated);

        return response()->json(['data' => $window], 201);
    }

    public function show(DaycareCapacityWindow $daycareCapacityWindow): JsonResponse
    {
        return response()->json(['data' => $daycareCapacityWindow]);
    }

    public function update(Request $request, DaycareCapacityWindow $daycareCapacityWindow): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['sometimes', 'string'],
            'max_dogs' => ['sometimes', 'integer', 'min:1'],
            'opens_at' => ['sometimes', 'date_format:H:i'],
            'closes_at' => ['sometimes', 'date_format:H:i'],
            'is_active' => ['boolean'],
        ]);

        $daycareCapacityWindow->update($validated);

        return response()->json(['data' => $daycareCapacityWindow->fresh()]);
    }

    public function destroy(DaycareCapacityWindow $daycareCapacityWindow): JsonResponse
    {
        $daycareCapacityWindow->delete();

        return response()->json(['data' => 'ok']);
    }
}
