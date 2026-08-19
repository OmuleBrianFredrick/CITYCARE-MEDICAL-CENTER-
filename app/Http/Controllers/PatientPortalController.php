<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\PatientPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class PatientPortalController extends Controller
{
    public function __construct(private readonly PatientPortalService $portal)
    {
    }

    public function show(Patient $patient): View
    {
        return view('patients.portal', compact('patient'));
    }

    public function provision(Patient $patient): RedirectResponse
    {
        try {
            $this->portal->provision($patient);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['portal' => $exception->getMessage()]);
        }

        return back()->with('status', 'Patient portal account provisioned and awaiting activation.');
    }

    public function activate(Patient $patient): RedirectResponse
    {
        try {
            $this->portal->activate($patient);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['portal' => $exception->getMessage()]);
        }

        return back()->with('status', 'Patient portal access activated successfully.');
    }

    public function disable(Patient $patient): RedirectResponse
    {
        try {
            $this->portal->disable($patient);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['portal' => $exception->getMessage()]);
        }

        return back()->with('status', 'Patient portal access disabled successfully.');
    }
}
