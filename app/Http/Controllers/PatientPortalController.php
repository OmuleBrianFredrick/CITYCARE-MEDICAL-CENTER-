<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\User;
use App\Services\FacilityAccessService;
use App\Services\PatientPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;
use RuntimeException;

class PatientPortalController extends Controller
{
    public function __construct(
        private readonly PatientPortalService $portal,
        private readonly FacilityAccessService $access,
    ) {}

    public function show(Request $request, Patient $patient): View
    {
        $this->access->assertPatientAccessible($request->user(), $patient);
        $patient->loadMissing('user');

        return view('patients.portal', compact('patient'));
    }

    public function provision(Request $request, Patient $patient): RedirectResponse
    {
        $this->access->assertPatientAccessible($request->user(), $patient);

        try {
            $user = $this->portal->provision($patient);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['portal' => $exception->getMessage()]);
        }

        return back()->with([
            'status' => 'Patient portal account provisioned. Share the secure setup link with the patient.',
            'portal_activation_url' => $this->activationUrl($user),
        ]);
    }

    public function invitation(Request $request, Patient $patient): RedirectResponse
    {
        $this->access->assertPatientAccessible($request->user(), $patient);
        $patient->loadMissing('user');

        if (! $patient->user) {
            return back()->withErrors(['portal' => 'The patient does not have a portal account.']);
        }

        $patient->forceFill(['portal_invited_at' => now()])->save();

        return back()->with([
            'status' => 'A new secure setup link has been generated. Any earlier link is no longer valid.',
            'portal_activation_url' => $this->activationUrl($patient->user),
        ]);
    }

    public function activate(Request $request, Patient $patient): RedirectResponse
    {
        $this->access->assertPatientAccessible($request->user(), $patient);

        try {
            $this->portal->activate($patient);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['portal' => $exception->getMessage()]);
        }

        return back()->with('status', 'Patient portal access activated successfully.');
    }

    public function disable(Request $request, Patient $patient): RedirectResponse
    {
        $this->access->assertPatientAccessible($request->user(), $patient);

        try {
            $this->portal->disable($patient);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['portal' => $exception->getMessage()]);
        }

        return back()->with('status', 'Patient portal access disabled successfully.');
    }

    private function activationUrl(User $user): string
    {
        $token = Password::broker()->createToken($user);

        return route('portal.activation.create', [
            'token' => $token,
            'email' => $user->email,
        ]);
    }
}
