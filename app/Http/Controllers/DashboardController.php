<?php

namespace App\Http\Controllers;

use App\Services\DashboardWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardWorkspaceService $workspace) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()->isPatient()) {
            return redirect()->route('portal.index');
        }

        return view('dashboard', $this->workspace->dashboard($request->user()));
    }
}
