@extends('layouts.app')

@section('title', 'Organization | CityCare')

@push('styles')
<style>
    .organization-page{max-width:1420px;padding:clamp(24px,4vw,44px)}.organization-heading{display:flex;justify-content:space-between;gap:22px;align-items:flex-start;margin-bottom:22px}.organization-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.organization-heading h1{margin:0;font-size:clamp(1.9rem,4vw,2.75rem);letter-spacing:-.045em}.organization-heading p{max-width:760px;margin:9px 0 0;color:var(--muted);line-height:1.55}.facility-switcher{min-width:260px;padding:14px}.facility-switcher label,.organization-form label{display:grid;gap:6px;color:var(--ink);font-size:.76rem;font-weight:800}.facility-switcher select,.organization-form input,.organization-form select,.organization-form textarea,.setting-form input,.setting-form select,.setting-form textarea{width:100%;min-width:0;padding:10px 11px;border:1px solid var(--line);border-radius:9px;background:#fff;color:var(--ink)}.facility-switcher select:focus,.organization-form input:focus,.organization-form select:focus,.organization-form textarea:focus,.setting-form input:focus,.setting-form select:focus,.setting-form textarea:focus{outline:3px solid rgba(37,99,235,.14);border-color:var(--blue)}.organization-alert{margin-bottom:18px}.organization-alert ul{margin:7px 0 0;padding-left:20px}.organization-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(330px,.7fr);gap:20px;align-items:start}.organization-stack{display:grid;gap:20px}.organization-card{padding:22px}.organization-card-head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:18px}.organization-card h2{margin:0;font-size:1.12rem}.organization-card-head p{margin:5px 0 0;color:var(--muted);font-size:.82rem;line-height:1.5}.organization-tag{display:inline-flex;padding:6px 9px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:.68rem;font-weight:850;white-space:nowrap}.organization-tag.is-muted{background:#f1f5f9;color:#475569}.organization-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px}.organization-form textarea,.setting-form textarea{min-height:92px;resize:vertical}.span-full{grid-column:1/-1}.organization-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:16px}.organization-button{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border:1px solid var(--blue);border-radius:9px;background:var(--blue);color:#fff;font-size:.8rem;font-weight:850;cursor:pointer;text-decoration:none}.organization-button.is-secondary{border-color:var(--line);background:#fff;color:var(--ink)}.department-list{display:grid;gap:12px}.department-card{padding:17px;border:1px solid var(--line);border-radius:13px}.department-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.department-head h3{margin:0;font-size:.98rem}.department-code{display:block;margin-top:4px;color:var(--blue);font-size:.7rem;font-weight:900;letter-spacing:.08em}.department-description{margin:11px 0;color:var(--muted);font-size:.8rem;line-height:1.5}.service-points{display:grid;gap:7px}.service-point{display:flex;justify-content:space-between;gap:14px;padding:10px 11px;border-radius:9px;background:#f8fafc;border:1px solid #edf2f7}.service-point strong{display:block;font-size:.82rem}.service-point small{display:block;margin-top:3px;color:var(--muted);font-size:.72rem;line-height:1.4}.service-point-state{color:#15803d;font-size:.7rem;font-weight:800}.service-point-state.is-inactive{color:#64748b}.organization-empty{padding:25px 16px;border:1px dashed var(--line);border-radius:11px;color:var(--muted);font-size:.82rem;text-align:center}.quick-form{display:grid;gap:12px}.settings-list{display:grid}.setting-row{padding:15px 0;border-bottom:1px solid #edf2f7}.setting-row:first-child{padding-top:0}.setting-row:last-child{padding-bottom:0;border-bottom:0}.setting-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.setting-key{font-size:.78rem;font-weight:850;overflow-wrap:anywhere}.setting-meta{margin-top:4px;color:var(--muted);font-size:.7rem}.setting-description{margin:7px 0;color:var(--muted);font-size:.77rem;line-height:1.45}.setting-value{margin-top:9px;padding:9px 10px;border-radius:8px;background:#f8fafc;color:#334155;font-size:.78rem;overflow-wrap:anywhere}.setting-form{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:end;margin-top:10px}.setting-form label{display:grid;gap:6px;color:var(--ink);font-size:.73rem;font-weight:800}.setting-form textarea{grid-row:span 1}.settings-notice{margin-bottom:15px;padding:11px 12px;border-radius:9px;background:#f8fafc;color:var(--muted);font-size:.78rem;line-height:1.5}.required-note{margin:12px 0 0;color:var(--muted);font-size:.72rem}.locked{display:inline-flex;align-items:center;gap:5px;color:#64748b;font-size:.68rem;font-weight:800}.locked::before{content:'LOCKED';padding:3px 5px;border-radius:5px;background:#f1f5f9;font-size:.58rem;letter-spacing:.06em}@media(max-width:1080px){.organization-grid{grid-template-columns:1fr}.organization-side{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}.organization-side .settings-card{grid-column:1/-1}}@media(max-width:760px){.organization-page{padding:24px 18px}.organization-heading{flex-direction:column}.facility-switcher{width:100%;min-width:0}.organization-form-grid,.organization-side{grid-template-columns:1fr}.span-full,.organization-side .settings-card{grid-column:auto}.organization-card-head,.department-head,.service-point{flex-direction:column}.setting-form{grid-template-columns:1fr}.organization-actions{align-items:stretch;flex-direction:column}.organization-button{width:100%}}
</style>
@endpush

@section('content')
<div class="organization-page">
    <header class="organization-heading">
        <div>
            <p class="organization-eyebrow">ORGANIZATION &amp; ADMINISTRATION</p>
            <h1>CityCare configuration</h1>
            <p>Manage the identity and operating structure of the facility selected below. Department and service-point choices are isolated to that facility.</p>
        </div>

        @if ($isSuperAdministrator && $facilities->count() > 1)
            <form class="card facility-switcher" method="GET" action="{{ route('organization.index') }}">
                <label>
                    Active facility
                    <select name="facility_id" onchange="this.form.submit()">
                        @foreach ($facilities as $availableFacility)
                            <option value="{{ $availableFacility->id }}" @selected($availableFacility->is($facility))>{{ $availableFacility->name }}</option>
                        @endforeach
                    </select>
                </label>
                <noscript><button class="organization-button" type="submit" style="margin-top:10px">Open facility</button></noscript>
            </form>
        @else
            <span class="organization-tag">{{ $facility->name }}</span>
        @endif
    </header>

    @if (session('status'))
        <div class="status organization-alert" role="status">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="error organization-alert" role="alert">
            <strong>Please review the highlighted information.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="organization-grid">
        <div class="organization-stack">
            <section class="card organization-card" aria-labelledby="facility-heading">
                <div class="organization-card-head">
                    <div>
                        <h2 id="facility-heading">Facility profile</h2>
                        <p>Canonical identity, contact information, regional defaults, and interface colors.</p>
                    </div>
                    <span class="organization-tag">FACILITY #{{ $facility->id }}</span>
                </div>

                @if ($canManageOrganization)
                    <form class="organization-form" method="POST" action="{{ route('organization.facility.update') }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="facility_id" value="{{ $facility->id }}">
                        <div class="organization-form-grid">
                            <label>Facility name<input name="name" value="{{ old('name', $facility->name) }}" required></label>
                            <label>Legal name<input name="legal_name" value="{{ old('legal_name', $facility->legal_name) }}"></label>
                            <label>Registration number<input name="registration_number" value="{{ old('registration_number', $facility->registration_number) }}"></label>
                            <label>Phone<input type="tel" name="phone" value="{{ old('phone', $facility->phone) }}"></label>
                            <label>Email<input type="email" name="email" value="{{ old('email', $facility->email) }}"></label>
                            <label>Website<input type="url" name="website" value="{{ old('website', $facility->website) }}" placeholder="https://"></label>
                            <label>Address line 1<input name="address_line1" value="{{ old('address_line1', $facility->address_line1) }}"></label>
                            <label>Address line 2<input name="address_line2" value="{{ old('address_line2', $facility->address_line2) }}"></label>
                            <label>City<input name="city" value="{{ old('city', $facility->city) }}"></label>
                            <label>District<input name="district" value="{{ old('district', $facility->district) }}"></label>
                            <label>Country<input name="country" value="{{ old('country', $facility->country) }}" required></label>
                            <label>Timezone<input name="timezone" value="{{ old('timezone', $facility->timezone) }}" required></label>
                            <label>Currency<input name="currency" value="{{ old('currency', $facility->currency) }}" maxlength="3" required></label>
                            <label>Primary color<input type="color" name="primary_color" value="{{ old('primary_color', $facility->primary_color) }}" required></label>
                            <label>Secondary color<input type="color" name="secondary_color" value="{{ old('secondary_color', $facility->secondary_color) }}" required></label>
                            <label>Accent color<input type="color" name="accent_color" value="{{ old('accent_color', $facility->accent_color) }}" required></label>
                        </div>
                        <div class="organization-actions"><button class="organization-button" type="submit">Save facility profile</button></div>
                    </form>
                @else
                    <div class="organization-empty">Your role can review this configuration but cannot change it.</div>
                @endif
            </section>

            <section class="card organization-card" aria-labelledby="structure-heading">
                <div class="organization-card-head">
                    <div>
                        <h2 id="structure-heading">Departments &amp; service points</h2>
                        <p>Service points are always attached to an active department in {{ $facility->name }}.</p>
                    </div>
                    <span class="organization-tag is-muted">{{ $departments->count() }} {{ str('department')->plural($departments->count()) }}</span>
                </div>

                <div class="department-list">
                    @forelse ($departments as $department)
                        <article class="department-card">
                            <div class="department-head">
                                <div>
                                    <h3>{{ $department->name }}</h3>
                                    <span class="department-code">{{ $department->code }}</span>
                                </div>
                                <span class="organization-tag {{ $department->is_active ? '' : 'is-muted' }}">{{ $department->is_active ? 'ACTIVE' : 'INACTIVE' }}</span>
                            </div>
                            <p class="department-description">{{ $department->description ?: 'No description configured.' }}</p>
                            <div class="service-points">
                                @forelse ($department->servicePoints as $point)
                                    <div class="service-point">
                                        <div>
                                            <strong>{{ $point->name }}</strong>
                                            <small>{{ $point->code }} · {{ str($point->type)->headline() }}{{ $point->location ? ' · '.$point->location : '' }}</small>
                                        </div>
                                        <span class="service-point-state {{ $point->is_active ? '' : 'is-inactive' }}">{{ $point->is_active ? 'Active' : 'Inactive' }}</span>
                                    </div>
                                @empty
                                    <div class="organization-empty">No service points are assigned to this department.</div>
                                @endforelse
                            </div>
                        </article>
                    @empty
                        <div class="organization-empty">No departments have been configured for this facility.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="organization-stack organization-side">
            @if ($canManageOrganization)
                <section class="card organization-card" aria-labelledby="department-create-heading">
                    <div class="organization-card-head">
                        <div>
                            <h2 id="department-create-heading">Add department</h2>
                            <p>Create an operational department at {{ $facility->name }}.</p>
                        </div>
                    </div>
                    <form class="organization-form quick-form" method="POST" action="{{ route('organization.departments.store') }}">
                        @csrf
                        <input type="hidden" name="facility_id" value="{{ $facility->id }}">
                        <label>Name<input name="name" value="{{ old('name') }}" required></label>
                        <label>Code<input name="code" value="{{ old('code') }}" placeholder="e.g. RADIOLOGY" required></label>
                        <label>Description<textarea name="description">{{ old('description') }}</textarea></label>
                        <button class="organization-button" type="submit">Create department</button>
                    </form>
                </section>

                <section class="card organization-card" aria-labelledby="service-point-create-heading">
                    <div class="organization-card-head">
                        <div>
                            <h2 id="service-point-create-heading">Add service point</h2>
                            <p>Attach a physical or service location to an active department.</p>
                        </div>
                    </div>
                    @if ($departments->where('is_active', true)->isEmpty())
                        <div class="organization-empty">Create an active department before adding a service point.</div>
                    @else
                        <form class="organization-form quick-form" method="POST" action="{{ route('organization.service-points.store') }}">
                            @csrf
                            <input type="hidden" name="facility_id" value="{{ $facility->id }}">
                            <label>
                                Department
                                <select name="department_id" required>
                                    <option value="">Select a department</option>
                                    @foreach ($departments->where('is_active', true) as $department)
                                        <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Name<input name="name" value="{{ old('name') }}" required></label>
                            <label>Code<input name="code" value="{{ old('code') }}" placeholder="e.g. RAD-ROOM-01" required></label>
                            <label>Type<input name="type" value="{{ old('type', 'service') }}" placeholder="e.g. radiology" required></label>
                            <label>Location<input name="location" value="{{ old('location') }}" placeholder="e.g. Ground Floor"></label>
                            <button class="organization-button" type="submit">Create service point</button>
                        </form>
                    @endif
                    <p class="required-note">Independent or orphaned service points are not permitted.</p>
                </section>
            @endif

            <section class="card organization-card settings-card" aria-labelledby="settings-heading">
                <div class="organization-card-head">
                    <div>
                        <h2 id="settings-heading">System settings</h2>
                        <p>Organization-wide settings keep their defined type, group, visibility, and description.</p>
                    </div>
                    <span class="organization-tag is-muted">GLOBAL</span>
                </div>

                @unless ($canManageSettings)
                    <div class="settings-notice">These values are read-only here. Only a super administrator can change organization-wide settings.</div>
                @endunless

                <div class="settings-list">
                    @forelse ($settings as $setting)
                        @php
                            $typedValue = $setting->typedValue();
                            $displayValue = is_array($typedValue) ? json_encode($typedValue, JSON_UNESCAPED_SLASHES) : ($setting->type === 'boolean' ? ($typedValue ? 'Enabled' : 'Disabled') : (string) $typedValue);
                        @endphp
                        <article class="setting-row">
                            <div class="setting-head">
                                <div>
                                    <div class="setting-key">{{ $setting->key }}</div>
                                    <div class="setting-meta">{{ str($setting->group)->headline() }} · {{ str($setting->type)->headline() }} · {{ $setting->is_public ? 'Public' : 'Private' }}</div>
                                </div>
                                <span class="locked">Metadata</span>
                            </div>
                            @if ($setting->description)
                                <p class="setting-description">{{ $setting->description }}</p>
                            @endif

                            @if ($canManageSettings)
                                <form class="setting-form" method="POST" action="{{ route('organization.settings.update', ['key' => $setting->key]) }}">
                                    @csrf
                                    @method('PUT')
                                    <label>
                                        Value
                                        @if ($setting->type === 'boolean')
                                            <select name="value" required>
                                                <option value="1" @selected((bool) $typedValue)>Enabled</option>
                                                <option value="0" @selected(! (bool) $typedValue)>Disabled</option>
                                            </select>
                                        @elseif ($setting->type === 'json')
                                            <textarea name="value" required>{{ $setting->value }}</textarea>
                                        @elseif ($setting->type === 'integer')
                                            <input type="number" step="1" name="value" value="{{ $setting->value }}" required>
                                        @elseif ($setting->type === 'float')
                                            <input type="number" step="any" name="value" value="{{ $setting->value }}" required>
                                        @else
                                            <input name="value" value="{{ $setting->value }}">
                                        @endif
                                    </label>
                                    <button class="organization-button" type="submit">Save</button>
                                </form>
                            @else
                                <div class="setting-value">{{ $displayValue === '' ? 'Not configured' : $displayValue }}</div>
                            @endif
                        </article>
                    @empty
                        <div class="organization-empty">No system settings are configured.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
