<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuditLogIndexRequest;
use App\Services\AuditLogService;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request, AuditLogService $service): View
    {
        $events = $service->query($request->validated())->paginate(
            (int) ($request->validated()['per_page'] ?? 25)
        )->withQueryString();

        return view('audit.index', compact('events'));
    }
}
