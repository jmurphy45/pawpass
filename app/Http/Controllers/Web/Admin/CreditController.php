<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dog;
use App\Services\DogCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CreditController extends Controller
{
    public function __construct(private DogCreditService $credits) {}

    public function goodwill(Request $request, Dog $dog): RedirectResponse
    {
        $request->validate([
            'credits' => ['required', 'integer', 'min:1'],
            'note'    => ['nullable', 'string'],
        ]);

        $this->credits->addGoodwill($dog, $request->credits, $request->note, auth()->user());

        return redirect()->route('admin.dogs.show', $dog)
            ->with('success', "Goodwill credits granted to {$dog->name}.");
    }

    public function correction(Request $request, Dog $dog): RedirectResponse
    {
        $request->validate([
            'delta' => ['required', 'integer', 'not_in:0'],
            'note'  => ['required', 'string'],
        ]);

        $this->credits->applyCorrection($dog, $request->delta, $request->note, auth()->user());

        return redirect()->route('admin.dogs.show', $dog)
            ->with('success', "Credit correction applied to {$dog->name}.");
    }

    public function transfer(Request $request, Dog $dog): RedirectResponse
    {
        $request->validate([
            'to_dog_id' => ['required', 'string'],
            'credits'   => ['required', 'integer', 'min:1'],
            'note'      => ['nullable', 'string'],
        ]);

        $toDog = Dog::find($request->to_dog_id);

        if (! $toDog) {
            return back()->with('error', 'Target dog not found.');
        }

        try {
            $this->credits->transfer($dog, $toDog, $request->credits);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.dogs.show', $dog)
            ->with('success', "Credits transferred from {$dog->name} to {$toDog->name}.");
    }
}
