<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Organization · CityCare Medical Center</title>
    <style>
        :root{--navy:#082f49;--blue:#0369a1;--sky:#e0f2fe;--cream:#f8faf7;--ink:#102a43;--muted:#627d98;--line:#d9e2ec;--yellow:#f4c542;--green:#15803d;--red:#b91c1c}
        *{box-sizing:border-box}body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink);background:var(--cream)}
        .shell{min-height:100vh;display:grid;grid-template-columns:250px 1fr}aside{padding:28px 20px;color:#fff;background:linear-gradient(180deg,var(--navy),#0c4a6e)}
        .brand{display:flex;align-items:center;gap:12px;margin-bottom:42px}.mark{width:42px;height:42px;display:grid;place-items:center;border-radius:12px;background:#fff;color:var(--navy);font-weight:900}.brand strong{display:block}.brand small{color:#bae6fd}
        nav{display:grid;gap:8px}nav a{padding:11px 12px;color:#e0f2fe;text-decoration:none;border-radius:10px}nav a:hover,nav a.active{background:rgba(255,255,255,.1);color:#fff}
        .side-note{margin-top:40px;padding:14px;border:1px solid rgba(255,255,255,.12);border-radius:14px;background:rgba(255,255,255,.06);font-size:.82rem;line-height:1.5}
        main{padding:34px clamp(22px,4vw,56px);max-width:1500px;width:100%}.top{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:28px}.eyebrow{color:var(--blue);font-size:.74rem;font-weight:850;letter-spacing:.14em}h1{margin:6px 0 8px;font-size:clamp(2rem,4vw,3rem);letter-spacing:-.05em}.muted{color:var(--muted);line-height:1.55}.back{color:var(--blue);font-weight:800;text-decoration:none}.status{padding:12px 16px;border-radius:12px;background:#ecfdf3;color:var(--green);border:1px solid #bbf7d0;margin-bottom:20px}
        .grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(320px,.65fr);gap:20px}.panel{background:#fff;border:1px solid var(--line);border-radius:20px;padding:22px;box-shadow:0 8px 28px rgba(16,42,67,.05);margin-bottom:20px}.panel h2{margin:0 0 6px;font-size:1.2rem}.panel-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:18px}.tag{padding:6px 9px;border-radius:999px;background:var(--sky);color:var(--blue);font-size:.72rem;font-weight:850}
        .form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}label{display:grid;gap:7px;font-size:.78rem;font-weight:800;color:var(--ink)}input,select,textarea{width:100%;padding:11px 12px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);font:inherit}textarea{min-height:90px;resize:vertical}.full{grid-column:1/-1}.actions{display:flex;justify-content:flex-end;gap:10px;margin-top:16px}.btn{border:0;border-radius:10px;padding:11px 15px;font-weight:850;cursor:pointer}.btn-primary{background:var(--blue);color:#fff}.btn-yellow{background:var(--yellow);color:#102a43}
        .department{border:1px solid var(--line);border-radius:15px;padding:16px;margin-top:12px}.department-head{display:flex;justify-content:space-between;gap:15px}.department h3{margin:0 0 4px}.code{font-size:.72rem;font-weight:900;color:var(--blue);letter-spacing:.08em}.points{display:grid;gap:8px;margin-top:13px}.point{display:flex;justify-content:space-between;gap:15px;padding:10px 12px;border-radius:10px;background:#f8fbfd;border:1px solid #edf2f7}.point small{color:var(--muted)}.empty{padding:20px;border:1px dashed var(--line);border-radius:12px;color:var(--muted)}.setting{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:13px 0;border-bottom:1px solid #edf2f7}.setting:last-child{border-bottom:0}.setting-key{font-weight:800;font-size:.82rem}.setting-value{color:var(--muted);word-break:break-word}.setting form{grid-column:1/-1;display:grid;grid-template-columns:1fr 110px 120px auto;gap:8px;align-items:center}.logout{display:inline-block;color:#fff;text-decoration:none;font-weight:800;font-size:.85rem}
        @media(max-width:1000px){.shell{grid-template-columns:1fr}aside{display:none}.grid{grid-template-columns:1fr}}@media(max-width:650px){main{padding:22px 16px}.form-grid{grid-template-columns:1fr}.full{grid-column:auto}.setting form{grid-template-columns:1fr}.top{flex-direction:column}}
    </style>
</head>
<body>
<div class="shell">
    <aside>
        <div class="brand"><div class="mark">CC</div><div><strong>CityCare</strong><small>Medical Center</small></div></div>
        <nav>
            <a href="{{ route('dashboard') }}">Dashboard</a>
            <a class="active" href="{{ route('organization.index') }}">Organization</a>
        </nav>
        <div class="side-note"><strong>Administration</strong><br>Configure facility identity, departments, service points and operational settings from one controlled workspace.</div>
        <div style="margin-top:24px"><form method="POST" action="{{ route('logout') }}">@csrf<button class="logout" style="background:none;border:0;padding:0;cursor:pointer">Sign out</button></form></div>
    </aside>
    <main>
        <div class="top"><div><div class="eyebrow">ORGANIZATION & ADMINISTRATION</div><h1>CityCare configuration</h1><p class="muted">Establish the medical center's identity, operational structure and configurable system behavior.</p></div><a class="back" href="{{ route('dashboard') }}">← Dashboard</a></div>
        @if(session('status'))<div class="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="status" style="background:#fff7ed;color:var(--red);border-color:#fed7aa"><strong>Please correct the highlighted configuration.</strong><ul style="margin:8px 0 0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <div class="grid">
            <section>
                <div class="panel">
                    <div class="panel-head"><div><h2>Facility profile</h2><p class="muted">The canonical identity and regional configuration for CityCare.</p></div><span class="tag">CORE CONFIGURATION</span></div>
                    <form method="POST" action="{{ route('organization.facility.update') }}">@csrf @method('PUT')
                        <div class="form-grid">
                            <label>Facility name<input name="name" value="{{ old('name', $facility?->name) }}" required></label>
                            <label>Legal name<input name="legal_name" value="{{ old('legal_name', $facility?->legal_name) }}"></label>
                            <label>Registration number<input name="registration_number" value="{{ old('registration_number', $facility?->registration_number) }}"></label>
                            <label>Phone<input name="phone" value="{{ old('phone', $facility?->phone) }}"></label>
                            <label>Email<input type="email" name="email" value="{{ old('email', $facility?->email) }}"></label>
                            <label>Website<input type="url" name="website" value="{{ old('website', $facility?->website) }}"></label>
                            <label>Address line 1<input name="address_line1" value="{{ old('address_line1', $facility?->address_line1) }}"></label>
                            <label>Address line 2<input name="address_line2" value="{{ old('address_line2', $facility?->address_line2) }}"></label>
                            <label>City<input name="city" value="{{ old('city', $facility?->city) }}"></label>
                            <label>District<input name="district" value="{{ old('district', $facility?->district) }}"></label>
                            <label>Country<input name="country" value="{{ old('country', $facility?->country ?? 'Uganda') }}" required></label>
                            <label>Timezone<input name="timezone" value="{{ old('timezone', $facility?->timezone ?? 'Africa/Kampala') }}" required></label>
                            <label>Currency<input name="currency" value="{{ old('currency', $facility?->currency ?? 'UGX') }}" maxlength="3" required></label>
                            <label>Primary blue<input name="primary_color" value="{{ old('primary_color', $facility?->primary_color ?? '#2563EB') }}" required></label>
                            <label>Secondary navy<input name="secondary_color" value="{{ old('secondary_color', $facility?->secondary_color ?? '#0F172A') }}" required></label>
                            <label>Accent yellow<input name="accent_color" value="{{ old('accent_color', $facility?->accent_color ?? '#F4C430') }}" required></label>
                        </div>
                        <div class="actions"><button class="btn btn-primary">Save facility profile</button></div>
                    </form>
                </div>

                <div class="panel">
                    <div class="panel-head"><div><h2>Departments & service points</h2><p class="muted">Model the operational areas and the physical/service locations used by CityCare.</p></div><span class="tag">OPERATIONS</span></div>
                    @forelse($departments as $department)
                        <div class="department"><div class="department-head"><div><h3>{{ $department->name }}</h3><span class="code">{{ $department->code }}</span></div><span class="tag">{{ $department->is_active ? 'ACTIVE' : 'INACTIVE' }}</span></div><p class="muted">{{ $department->description ?: 'No description configured.' }}</p><div class="points">@forelse($department->servicePoints as $point)<div class="point"><div><strong>{{ $point->name }}</strong><br><small>{{ $point->code }} · {{ $point->type }}{{ $point->location ? ' · '.$point->location : '' }}</small></div><span>{{ $point->is_active ? 'Active' : 'Inactive' }}</span></div>@empty<div class="empty">No service points configured for this department.</div>@endforelse</div></div>
                    @empty<div class="empty">No departments have been configured yet.</div>@endforelse
                </div>
            </section>

            <aside style="background:none;padding:0;color:inherit">
                <div class="panel">
                    <div class="panel-head"><div><h2>Add department</h2><p class="muted">Create a new operational department.</p></div></div>
                    <form method="POST" action="{{ route('organization.departments.store') }}">@csrf
                        <label>Name<input name="name" required></label><br>
                        <label>Code<input name="code" placeholder="e.g. OPD" required></label><br>
                        <label>Description<textarea name="description"></textarea></label>
                        <div class="actions"><button class="btn btn-primary">Create department</button></div>
                    </form>
                </div>
                <div class="panel">
                    <div class="panel-head"><div><h2>Add service point</h2><p class="muted">Connect a service location to a department.</p></div></div>
                    <form method="POST" action="{{ route('organization.service-points.store') }}">@csrf
                        <label>Department<select name="department_id"><option value="">Independent service point</option>@foreach($departments as $department)<option value="{{ $department->id }}">{{ $department->name }}</option>@endforeach</select></label><br>
                        <label>Name<input name="name" required></label><br>
                        <label>Code<input name="code" placeholder="e.g. OPD-ROOM-01" required></label><br>
                        <label>Type<input name="type" value="service" required></label><br>
                        <label>Location<input name="location" placeholder="Ground Floor"></label>
                        <div class="actions"><button class="btn btn-yellow">Create service point</button></div>
                    </form>
                </div>
                <div class="panel">
                    <div class="panel-head"><div><h2>System settings</h2><p class="muted">Typed settings prepared for operational and notification modules.</p></div></div>
                    @forelse($settings as $setting)<div class="setting"><div><div class="setting-key">{{ $setting->key }}</div><small class="muted">{{ $setting->group }} · {{ $setting->type }}</small></div><div class="setting-value">{{ is_array($setting->typedValue()) ? json_encode($setting->typedValue()) : (string) $setting->typedValue() }}</div><form method="POST" action="{{ route('organization.settings.update', ['key' => $setting->key]) }}">@csrf @method('PUT')<input name="value" value="{{ $setting->value }}"><select name="type"><option value="string" @selected($setting->type==='string')>string</option><option value="boolean" @selected($setting->type==='boolean')>boolean</option><option value="integer" @selected($setting->type==='integer')>integer</option><option value="float" @selected($setting->type==='float')>float</option><option value="json" @selected($setting->type==='json')>json</option></select><input name="group" value="{{ $setting->group }}"><button class="btn btn-primary">Save</button></form></div>@empty<div class="empty">No settings configured.</div>@endforelse
                </div>
            </aside>
        </div>
    </main>
</div>
</body>
</html>
