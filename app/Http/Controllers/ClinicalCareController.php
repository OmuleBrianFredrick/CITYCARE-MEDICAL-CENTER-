<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClinicalNoteRequest;
use App\Http\Requests\StoreClinicalVitalRequest;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalNote;
use App\Services\ClinicalCareService;
use App\Services\FacilityAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClinicalCareController extends Controller
{
    public function __construct(
        private readonly ClinicalCareService $care,
        private readonly FacilityAccessService $facilityAccess,
    ) {}

    public function storeNote(StoreClinicalNoteRequest $request, ClinicalEncounter $encounter): RedirectResponse
    {
        $this->facilityAccess->assertEncounterAccessible($request->user(), $encounter);
        $note = $this->care->saveNote($encounter, $request->user(), $request->validated());

        return back()->with('status', "Clinical note #{$note->id} saved successfully.");
    }

    public function finalizeNote(Request $request, ClinicalNote $note): RedirectResponse
    {
        $note->loadMissing('encounter');
        $this->facilityAccess->assertEncounterAccessible($request->user(), $note->encounter);
        $this->care->finalizeNote($note);

        return back()->with('status', 'Clinical note finalized successfully.');
    }

    public function storeVitals(StoreClinicalVitalRequest $request, ClinicalEncounter $encounter): RedirectResponse
    {
        $this->facilityAccess->assertEncounterAccessible($request->user(), $encounter);
        $vital = $this->care->recordVitals($encounter, $request->user(), $request->validated());

        return back()->with('status', "Clinical vitals #{$vital->id} recorded successfully.");
    }
}
