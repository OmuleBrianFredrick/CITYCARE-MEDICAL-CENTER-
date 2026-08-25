<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CityCare Medical Center')</title>
    @if (file_exists(public_path('hot')) || file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        :root{--navy:#082f49;--navy-deep:#05263b;--blue:#2563eb;--cream:#f7fafc;--ink:#102a43;--muted:#627d98;--line:#d9e2ec;--green:#15803d;--red:#b91c1c;--sidebar-width:258px}*{box-sizing:border-box}body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:var(--ink);background:var(--cream)}a{color:var(--blue)}button,input,select,textarea{font:inherit}.app-shell{min-height:100vh;display:grid;grid-template-columns:var(--sidebar-width) minmax(0,1fr)}.sidebar{display:flex;flex-direction:column;padding:24px 16px;background:linear-gradient(180deg,var(--navy),var(--navy-deep));color:#fff}.brand{display:flex;align-items:center;gap:11px;padding:0 8px 28px}.brand-mark{display:grid;place-items:center;width:38px;height:38px;border-radius:11px;background:#fff;color:var(--navy);font-size:.78rem;font-weight:900;letter-spacing:.04em}.brand strong{display:block;font-size:.98rem}.brand small{display:block;margin-top:2px;color:#bae6fd;font-size:.74rem}.sidebar-label{padding:0 10px 8px;color:#94c7e2;font-size:.68rem;font-weight:800;letter-spacing:.12em}.sidebar-nav{display:grid;gap:4px}.sidebar-nav a{display:block;padding:10px 12px;border-radius:9px;color:#ddecf7;font-size:.9rem;font-weight:650;text-decoration:none}.sidebar-nav a:hover,.sidebar-nav a[aria-current="page"]{background:rgba(255,255,255,.12);color:#fff}.sidebar-foot{margin-top:auto;padding:18px 8px 0;border-top:1px solid rgba(255,255,255,.13)}.sidebar-user{margin:0 0 14px;color:#fff;font-size:.88rem;font-weight:750}.sidebar-role{display:block;margin-top:3px;color:#b9ddf1;font-size:.76rem;font-weight:500}.sign-out{width:100%;padding:10px 12px;border:1px solid rgba(255,255,255,.32);border-radius:9px;background:transparent;color:#fff;cursor:pointer;font-weight:750;text-align:left}.sign-out:hover{background:rgba(255,255,255,.1)}.workspace-main{min-width:0}.workspace-topbar{min-height:72px;display:flex;justify-content:space-between;align-items:center;gap:18px;padding:14px clamp(20px,3vw,44px);background:#fff;border-bottom:1px solid var(--line)}.workspace-context{color:var(--muted);font-size:.84rem}.workspace-context strong{display:block;color:var(--ink);font-size:.92rem}.workspace-role{display:inline-block;margin-top:2px;color:var(--blue);font-size:.78rem;font-weight:750}.content{max-width:1500px;margin:0 auto}.card{background:#fff;border:1px solid var(--line);border-radius:16px}.status{padding:12px 16px;border-radius:10px;background:#ecfdf3;color:var(--green)}.error{padding:12px 16px;border-radius:10px;background:#fef2f2;color:var(--red)}
        @media(max-width:900px){.app-shell{grid-template-columns:1fr}.sidebar{gap:14px;padding:14px 18px}.brand{padding:0}.sidebar-label{display:none}.sidebar-nav{display:flex;overflow-x:auto;gap:6px}.sidebar-nav a{white-space:nowrap}.sidebar-foot{display:flex;align-items:center;gap:14px;margin:0 0 0 auto;padding:0;border:0}.sidebar-user{display:none}.sign-out{width:auto;white-space:nowrap}.workspace-topbar{min-height:58px;padding:10px 18px}.workspace-context{font-size:.76rem}}
    </style>
    @stack('styles')
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('dashboard') }}" aria-label="CityCare dashboard" style="color:inherit;text-decoration:none">
            <span class="brand-mark">CC</span>
            <span><strong>CityCare</strong><small>Medical Center</small></span>
        </a>
        @auth
            <div class="sidebar-label">WORKSPACE</div>
            <nav class="sidebar-nav" aria-label="Primary navigation">
                @foreach(($shell['navigation'] ?? []) as $item)
                    <a href="{{ $item['url'] }}" @if(request()->routeIs($item['active'])) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <div class="sidebar-foot">
                <p class="sidebar-user">{{ auth()->user()->name }}<span class="sidebar-role">{{ $shell['roleLabel'] ?? 'CityCare account' }}</span></p>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="sign-out" type="submit">Sign out</button></form>
            </div>
        @endauth
    </aside>
    <div class="workspace-main">
        @auth
            <header class="workspace-topbar">
                <div class="workspace-context"><strong>{{ $shell['facility']?->name ?? 'CityCare Medical Center' }}</strong><span>Secure, permission-aware clinical workspace</span></div>
                <span class="workspace-role">{{ $shell['roleLabel'] ?? 'CityCare account' }}</span>
            </header>
        @endauth
        <main class="content">@yield('content')</main>
    </div>
</div>
@stack('scripts')
</body>
</html>
