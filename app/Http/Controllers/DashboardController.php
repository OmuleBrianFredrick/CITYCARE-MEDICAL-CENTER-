<?php

namespace App\Http\Controllers;

use App\Services\DashboardWorkspaceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardWorkspaceService $workspace) {}

    public function index(Request $request): View
    {
        return view('dashboard', $this->workspace->dashboard($request->user()));
    }
}
