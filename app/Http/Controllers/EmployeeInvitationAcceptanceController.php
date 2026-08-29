<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptEmployeeInvitationRequest;
use App\Services\EmployeeInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmployeeInvitationAcceptanceController extends Controller
{
    public function __construct(private readonly EmployeeInvitationService $invitations) {}

    public function create(string $token): View
    {
        $invitation = $this->invitations->findPending($token);

        abort_unless($invitation, 404, 'This staff setup link is invalid or has expired.');

        return view('auth.staff-invitation', compact('invitation', 'token'));
    }

    public function store(AcceptEmployeeInvitationRequest $request): RedirectResponse
    {
        $credentials = $request->validated();
        $this->invitations->accept(
            $credentials['token'],
            $credentials['email'],
            $credentials['password'],
        );

        return redirect()
            ->route('login')
            ->with('status', 'Your staff account is ready. Sign in with your new password.');
    }
}
