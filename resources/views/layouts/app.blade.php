<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CityCare Medical Center')</title>
    <style>
        :root{--navy:#082f49;--blue:#2563eb;--cream:#f7fafc;--ink:#102a43;--muted:#627d98;--line:#d9e2ec;--green:#15803d;--red:#b91c1c}*{box-sizing:border-box}body{margin:0;font-family:Inter,ui-sans-serif,system-ui;color:var(--ink);background:var(--cream)}a{color:var(--blue)}button,input,select,textarea{font:inherit}.shell{min-height:100vh}.nav{background:var(--navy);color:#fff;padding:14px 24px;display:flex;align-items:center;gap:20px}.nav strong{margin-right:auto}.nav a{color:#e0f2fe;text-decoration:none}.nav form{margin:0}.nav button{border:0;background:none;color:#fff;cursor:pointer}.content{max-width:1450px;margin:0 auto}.card{background:#fff;border:1px solid var(--line);border-radius:16px}.status{padding:12px 16px;border-radius:10px;background:#ecfdf3;color:var(--green)}.error{padding:12px 16px;border-radius:10px;background:#fef2f2;color:var(--red)}
    </style>
</head>
<body>
<div class="shell">
    <header class="nav">
        <strong>CityCare Medical Center</strong>
        @auth
            <a href="{{ route('dashboard') }}">Dashboard</a>
            @if(auth()->user()->hasPermissionTo('patients.view'))<a href="{{ route('patients.index') }}">Patients</a>@endif
            @if(auth()->user()->hasPermissionTo('appointments.manage'))<a href="{{ route('appointments.index') }}">Appointments</a>@endif
            @if(auth()->user()->hasPermissionTo('organization.view'))<a href="{{ route('organization.index') }}">Organization</a>@endif
            <form method="POST" action="{{ route('logout') }}">@csrf<button>Sign out</button></form>
        @endauth
    </header>
    <main class="content">@yield('content')</main>
</div>
</body>
</html>
