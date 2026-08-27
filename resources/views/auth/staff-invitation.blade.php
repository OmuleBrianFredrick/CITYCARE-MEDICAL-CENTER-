<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set up your staff account · CityCare Medical Center</title>
    <style>
        :root{--navy:#082f49;--blue:#2563eb;--ink:#102a43;--muted:#627d98;--line:#d9e2ec;--red:#b91c1c}*{box-sizing:border-box}body{min-height:100vh;margin:0;display:grid;place-items:center;padding:24px;background:radial-gradient(circle at 10% 5%,#dbeafe 0,transparent 34%),linear-gradient(145deg,#f8fafc,#eaf3f8);font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:var(--ink)}.setup-shell{width:min(100%,980px);display:grid;grid-template-columns:minmax(260px,.85fr) minmax(320px,1.15fr);overflow:hidden;border:1px solid rgba(8,47,73,.13);border-radius:24px;background:#fff;box-shadow:0 28px 70px rgba(8,47,73,.14)}.setup-intro{padding:clamp(34px,5vw,58px);background:linear-gradient(155deg,#082f49,#0c4a6e);color:#fff}.brand{display:flex;align-items:center;gap:12px}.brand-mark{display:grid;place-items:center;width:42px;height:42px;border-radius:12px;background:#fff;color:var(--navy);font-size:.8rem;font-weight:900}.brand strong{display:block}.brand small{display:block;margin-top:2px;color:#bae6fd;font-size:.76rem}.setup-intro h1{margin:64px 0 15px;font-size:clamp(2rem,4vw,3.1rem);line-height:1.02;letter-spacing:-.055em}.setup-intro p{margin:0;color:#d7edf8;line-height:1.65}.privacy{margin-top:40px!important;padding-top:22px;border-top:1px solid rgba(255,255,255,.18);font-size:.82rem}.setup-form{padding:clamp(34px,5vw,58px)}.eyebrow{margin:0 0 8px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.setup-form h2{margin:0;font-size:clamp(1.6rem,3vw,2.2rem);letter-spacing:-.04em}.sub{margin:10px 0 24px;color:var(--muted);line-height:1.55}.invitation-meta{margin:0 0 22px;padding:12px 14px;border:1px solid var(--line);border-radius:10px;background:#f8fafc;color:var(--muted);font-size:.8rem;line-height:1.5}.field{display:grid;gap:7px;margin-bottom:18px}.field label{font-size:.82rem;font-weight:800}.field input{width:100%;padding:13px 14px;border:1px solid var(--line);border-radius:11px;color:var(--ink);outline:none}.field input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,99,235,.12)}.hint{margin:0;color:var(--muted);font-size:.75rem;line-height:1.45}.error{margin:0 0 20px;padding:13px 15px;border-radius:10px;background:#fef2f2;color:var(--red);font-size:.84rem}.submit{width:100%;margin-top:5px;padding:13px;border:0;border-radius:11px;background:var(--blue);color:#fff;font-weight:850;cursor:pointer}.login-link{display:block;margin-top:20px;text-align:center;font-size:.82rem;font-weight:750;text-decoration:none}@media(max-width:760px){.setup-shell{grid-template-columns:1fr}.setup-intro{padding:28px}.setup-intro h1{margin:34px 0 12px;font-size:2.1rem}.privacy{display:none}.setup-form{padding:30px 24px}}
    </style>
</head>
<body>
<main class="setup-shell">
    <section class="setup-intro">
        <div class="brand"><span class="brand-mark">CC</span><span><strong>CityCare</strong><small>Medical Center</small></span></div>
        <h1>Welcome to your secure workspace.</h1>
        <p>Create the private password you will use for your assigned CityCare responsibilities. Your permissions and facility access are already bound to this invitation.</p>
        <p class="privacy">This setup link is personal, single-use, and expires at {{ $invitation->expires_at->format('d M Y, H:i') }}. Do not forward it.</p>
    </section>
    <section class="setup-form">
        <p class="eyebrow">STAFF ACCOUNT SETUP</p>
        <h2>Create your password</h2>
        <p class="sub">Hello {{ $invitation->name }}. Confirm your invited email address to activate your staff account.</p>
        <div class="invitation-meta"><strong>{{ $invitation->user?->staffProfile?->department?->facility?->name }}</strong><br>{{ $invitation->user?->staffProfile?->department?->name }} · {{ str($invitation->role_slug)->replace('-', ' ')->headline() }}</div>

        @if ($errors->any())
            <div class="error" role="alert">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>
        @endif

        <form method="POST" action="{{ route('staff-invitations.accept.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="field">
                <label for="email">Invited email address</label>
                <input id="email" name="email" type="email" value="{{ old('email', $invitation->email) }}" autocomplete="email" required>
            </div>
            <div class="field">
                <label for="password">New password</label>
                <input id="password" name="password" type="password" minlength="12" autocomplete="new-password" required>
                <p class="hint">Use at least 12 characters and do not reuse a password from another service.</p>
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" minlength="12" autocomplete="new-password" required>
            </div>
            <button class="submit" type="submit">Activate staff account</button>
        </form>
        <a class="login-link" href="{{ route('login') }}">Return to sign in</a>
    </section>
</main>
</body>
</html>
