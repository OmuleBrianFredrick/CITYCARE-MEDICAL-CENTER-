<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PatientPortalActivationController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.patient-portal-activation', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = User::query()
            ->where('email', strtolower(trim($credentials['email'])))
            ->with('patientProfile')
            ->first();

        if (! $user?->isPatient() || ! $user->patientProfile) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This portal setup link is invalid or has expired.']);
        }

        $status = Password::broker()->reset(
            $credentials,
            function (User $account, string $password): void {
                $account->forceFill([
                    'password' => Hash::make($password),
                    'is_active' => true,
                    'email_verified_at' => $account->email_verified_at ?? now(),
                ])->setRememberToken(Str::random(60));
                $account->save();

                $account->patientProfile->forceFill([
                    'portal_activated_at' => now(),
                    'portal_disabled_at' => null,
                ])->save();

                event(new PasswordReset($account));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This portal setup link is invalid or has expired. Request a new link from CityCare.']);
        }

        return redirect()->route('login')->with('status', 'Your patient portal is ready. Sign in with your new password.');
    }
}
