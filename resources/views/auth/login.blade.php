<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in · CityCare Medical Center</title>
    <style>
        :root {
            --blue-950: #082f49;
            --blue-900: #0c4a6e;
            --blue-700: #0369a1;
            --blue-500: #0ea5e9;
            --cream: #f8faf7;
            --yellow: #f4c542;
            --ink: #102a43;
            --muted: #627d98;
            --line: #d9e2ec;
            --danger: #b42318;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--ink);
            background: linear-gradient(135deg, var(--blue-950), var(--blue-700) 58%, #eaf5fb);
        }
        .page { min-height: 100vh; display: grid; place-items: center; padding: 28px; }
        .shell {
            width: min(1080px, 100%);
            min-height: 640px;
            display: grid;
            grid-template-columns: 1.05fr .95fr;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,.24);
            border-radius: 30px;
            background: rgba(255,255,255,.96);
            box-shadow: 0 28px 80px rgba(2, 30, 48, .30);
        }
        .brand-panel { padding: 56px; color: #fff; background: linear-gradient(160deg, #075985, #0c4a6e 65%, #082f49); position: relative; overflow: hidden; }
        .brand-panel::after { content: ""; position: absolute; width: 300px; height: 300px; right: -120px; bottom: -120px; border-radius: 50%; border: 55px solid rgba(244,197,66,.14); }
        .mark { width: 54px; height: 54px; display: grid; place-items: center; border-radius: 16px; background: #fff; color: var(--blue-900); font-weight: 900; letter-spacing: -.05em; }
        .eyebrow { margin-top: 70px; color: #bae6fd; font-size: .76rem; font-weight: 800; letter-spacing: .16em; }
        h1 { max-width: 440px; margin: 14px 0 20px; font-size: clamp(2.5rem, 5vw, 4.3rem); line-height: .98; letter-spacing: -.055em; }
        .brand-copy { max-width: 500px; color: #d9f1fb; font-size: 1.04rem; line-height: 1.7; }
        .trust { position: absolute; left: 56px; right: 56px; bottom: 52px; display: flex; gap: 12px; flex-wrap: wrap; }
        .trust span { padding: 9px 12px; border: 1px solid rgba(255,255,255,.16); border-radius: 999px; background: rgba(255,255,255,.08); font-size: .8rem; }
        .form-panel { display: flex; align-items: center; padding: 56px; background: var(--cream); }
        .form-wrap { width: min(420px, 100%); margin: auto; }
        .form-wrap h2 { margin: 0 0 8px; font-size: 2rem; letter-spacing: -.035em; }
        .sub { margin: 0 0 30px; color: var(--muted); line-height: 1.6; }
        label { display: block; margin: 0 0 18px; font-size: .88rem; font-weight: 750; }
        input { width: 100%; margin-top: 8px; padding: 14px 15px; border: 1px solid var(--line); border-radius: 13px; background: #fff; color: var(--ink); font: inherit; outline: none; transition: .18s ease; }
        input:focus { border-color: var(--blue-500); box-shadow: 0 0 0 4px rgba(14,165,233,.12); }
        .row { display: flex; justify-content: space-between; gap: 12px; align-items: center; margin: 4px 0 22px; }
        .remember { display: flex; align-items: center; gap: 8px; color: var(--muted); font-weight: 600; }
        .remember input { width: auto; margin: 0; }
        button { width: 100%; padding: 14px 18px; border: 0; border-radius: 13px; background: var(--blue-700); color: #fff; font: inherit; font-weight: 800; cursor: pointer; box-shadow: 0 10px 24px rgba(3,105,161,.20); }
        button:hover { background: var(--blue-900); }
        .alert { margin-bottom: 18px; padding: 13px 15px; border-radius: 12px; background: #fff0ef; color: var(--danger); border: 1px solid #ffd1cc; font-size: .9rem; }
        .status { margin-bottom: 18px; padding: 13px 15px; border-radius: 12px; background: #eefbf3; color: #166534; border: 1px solid #bbf7d0; font-size: .9rem; }
        .footer { margin-top: 24px; color: var(--muted); font-size: .78rem; line-height: 1.5; text-align: center; }
        @media (max-width: 820px) { .shell { grid-template-columns: 1fr; min-height: auto; } .brand-panel { padding: 38px; min-height: 370px; } .eyebrow { margin-top: 45px; } .trust { left: 38px; right: 38px; bottom: 34px; } .form-panel { padding: 38px; } }
    </style>
</head>
<body>
<div class="page">
    <main class="shell">
        <section class="brand-panel">
            <div class="mark" aria-hidden="true">CC</div>
            <div class="eyebrow">CITYCARE MEDICAL CENTER</div>
            <h1>Care, connected.</h1>
            <p class="brand-copy">A secure workspace for CityCare staff and a trusted digital experience for patients. Sign in to continue to the services available to your account.</p>
            <div class="trust"><span>Secure access</span><span>Patient-first</span><span>Uganda · EAT</span></div>
        </section>
        <section class="form-panel">
            <div class="form-wrap">
                <h2>Welcome back</h2>
                <p class="sub">Sign in with the email address and password associated with your CityCare account.</p>

                @if (session('status'))
                    <div class="status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}">
                    @csrf
                    <label for="email">Email address
                        <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                    </label>
                    <label for="password">Password
                        <input id="password" name="password" type="password" autocomplete="current-password" required>
                    </label>
                    <div class="row">
                        <label class="remember" for="remember"><input id="remember" name="remember" type="checkbox" value="1"> Remember me</label>
                    </div>
                    <button type="submit">Sign in securely</button>
                </form>
                <p class="footer">Access is controlled by your CityCare account status, role, and permissions. If you need access changed, contact an authorized administrator.</p>
            </div>
        </section>
    </main>
</div>
</body>
</html>
