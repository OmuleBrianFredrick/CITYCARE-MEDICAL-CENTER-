<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard · CityCare Medical Center</title>
    <style>
        :root { --navy:#082f49; --blue:#0369a1; --sky:#e0f2fe; --cream:#f8faf7; --ink:#102a43; --muted:#627d98; --line:#d9e2ec; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; color:var(--ink); background:var(--cream); }
        .shell { min-height:100vh; display:grid; grid-template-columns:250px 1fr; }
        aside { padding:28px 20px; color:#fff; background:linear-gradient(180deg,var(--navy),#0c4a6e); }
        .brand { display:flex; align-items:center; gap:12px; margin-bottom:44px; }
        .mark { width:42px; height:42px; display:grid; place-items:center; border-radius:12px; background:#fff; color:var(--navy); font-weight:900; }
        .brand strong { display:block; } .brand small { color:#bae6fd; }
        nav { display:grid; gap:8px; } nav a { padding:11px 12px; color:#e0f2fe; text-decoration:none; border-radius:10px; } nav a.active, nav a:hover { background:rgba(255,255,255,.10); color:#fff; }
        .side-note { margin-top:40px; padding:14px; border:1px solid rgba(255,255,255,.12); border-radius:14px; background:rgba(255,255,255,.06); font-size:.82rem; line-height:1.5; }
        main { padding:34px clamp(22px,4vw,56px); }
        header { display:flex; justify-content:space-between; gap:20px; align-items:center; margin-bottom:34px; }
        .eyebrow { color:var(--blue); font-size:.74rem; font-weight:850; letter-spacing:.14em; } h1 { margin:6px 0 8px; font-size:clamp(2rem,4vw,3.2rem); letter-spacing:-.05em; } .muted { color:var(--muted); }
        .user { display:flex; align-items:center; gap:12px; } .pill { padding:7px 10px; border-radius:999px; background:var(--sky); color:var(--blue); font-size:.76rem; font-weight:800; }
        .logout { padding:10px 13px; border:1px solid var(--line); border-radius:10px; background:#fff; color:var(--ink); font-weight:750; cursor:pointer; }
        .cards { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:18px; margin-bottom:22px; }
        .card { padding:22px; background:#fff; border:1px solid var(--line); border-radius:18px; box-shadow:0 8px 28px rgba(16,42,67,.05); } .card span { color:var(--muted); font-size:.82rem; } .card strong { display:block; margin-top:10px; font-size:1.35rem; }
        .hero { padding:28px; border-radius:20px; color:#fff; background:linear-gradient(135deg,var(--blue),var(--navy)); position:relative; overflow:hidden; } .hero::after { content:""; position:absolute; width:180px;height:180px;right:-60px;top:-60px;border-radius:50%;border:35px solid rgba(244,197,66,.18); }
        .hero h2 { margin:0 0 8px; font-size:1.65rem; } .hero p { max-width:700px; color:#d9f1fb; line-height:1.6; }
        @media(max-width:850px){ .shell{grid-template-columns:1fr} aside{display:none}.cards{grid-template-columns:1fr} header{align-items:flex-start;flex-direction:column}.user{width:100%;justify-content:space-between} }
    </style>
</head>
<body>
<div class="shell">
    <aside>
        <div class="brand"><div class="mark">CC</div><div><strong>CityCare</strong><small>Medical Center</small></div></div>
        <nav><a class="active" href="{{ route('dashboard') }}">Dashboard</a></nav>
        <div class="side-note"><strong>Secure workspace</strong><br>Access to clinical and operational modules is controlled by your assigned permissions.</div>
    </aside>
    <main>
        <header>
            <div><div class="eyebrow">CITYCARE MEDICAL CENTER</div><h1>Good to see you, {{ auth()->user()->name }}.</h1><div class="muted">Your workspace is ready. Modules will appear here as they are enabled for your role.</div></div>
            <div class="user"><span class="pill">{{ ucfirst(str_replace('-', ' ', auth()->user()->user_type)) }}</span><form method="POST" action="{{ route('logout') }}">@csrf<button class="logout" type="submit">Sign out</button></form></div>
        </header>
        <section class="cards">
            <div class="card"><span>Account status</span><strong>{{ auth()->user()->isActive() ? 'Active' : 'Inactive' }}</strong></div>
            <div class="card"><span>Access roles</span><strong>{{ auth()->user()->roles()->count() }}</strong></div>
            <div class="card"><span>Last sign-in</span><strong>{{ auth()->user()->last_login_at?->diffForHumans() ?? 'First sign-in' }}</strong></div>
        </section>
        <section class="hero">
            <h2>CityCare workspace foundation</h2>
            <p>Authentication, account status, role resolution, permission enforcement, and secure session handling are now established. Clinical and operational modules will be connected to this workspace in their respective development phases.</p>
        </section>
    </main>
</div>
</body>
</html>
