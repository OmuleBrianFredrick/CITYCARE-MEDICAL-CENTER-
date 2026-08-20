<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClinicalNoteRequest;
use App\Http\Requests\StoreClinicalVitalRequest;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalNote;
use App\Models\ClinicalVital;
use App\Services\ClinicalCareService;
use Illuminate\Http\RedirectResponse;

class ClinicalCareController extends Controller
{
    public function __construct(private readonly ClinicalCareService $care)
    {
    }

    public function storeNote(StoreClinicalNoteRequest $request, ClinicalEncounter $encounter): RedirectResponse
    {
        $note = $this->care->saveNote($encounter, $request->user(), $request->validated());

        return back()->with('status', "Clinical note #{$note->id} saved successfully.");
    }

    public function finalizeNote(ClinicalNote $note): RedirectResponse
    {
        $this->care->finalizeNote($note);

        return back()->with('status', 'Clinical note finalized successfully.');
    }

    public function storeVitals(StoreClinicalVitalRequest $request, ClinicalEncounter $encounter): RedirectResponse
    {
        $vital = $this->care->recordVitals($encounter, $request->user(), $request->validated());

        return back()->with('status', "Clinical vitals #{$vital->id} recorded successfully.");
    }
}
