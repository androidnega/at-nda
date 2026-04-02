<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VenueController extends Controller
{
    public function index(): View
    {
        $venues = Venue::withCount('courses')->orderBy('name')->paginate(15);
        return view('admin.venues', compact('venues'));
    }

    public function create(): View
    {
        return view('admin.venue-form', ['venue' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'building' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1',
        ]);
        Venue::create($validated);
        return redirect()->route('dashboard.venues.index')->with('success', 'Venue created.');
    }

    public function edit(Venue $venue): View
    {
        return view('admin.venue-form', ['venue' => $venue]);
    }

    public function update(Request $request, Venue $venue): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'building' => 'nullable|string|max:255',
            'capacity' => 'nullable|integer|min:1',
        ]);
        $venue->update($validated);
        return redirect()->route('dashboard.venues.index')->with('success', 'Venue updated.');
    }

    public function destroy(Venue $venue): RedirectResponse
    {
        if ($venue->courses()->count() > 0) {
            return back()->with('error', 'Cannot delete venue with assigned courses.');
        }
        $venue->delete();
        return redirect()->route('dashboard.venues.index')->with('success', 'Venue deleted.');
    }
}
