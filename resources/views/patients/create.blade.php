@extends('layouts.app')

@section('title', 'Register Patient · CityCare Medical Center')

@push('styles')
<style>
    .registration-page{max-width:1120px;padding:clamp(24px,4vw,42px)}.registration-heading{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}.registration-eyebrow{margin:0 0 7px;color:var(--blue);font-size:.72rem;font-weight:850;letter-spacing:.14em}.registration-heading h1{margin:0;font-size:clamp(1.85rem,4vw,2.65rem);letter-spacing:-.045em}.registration-heading p{max-width:720px;margin:9px 0 0;color:var(--muted);line-height:1.55}.registration-back{display:inline-flex;padding:10px 13px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);font-size:.85rem;font-weight:800;text-decoration:none}.registration-card{margin-bottom:18px;padding:22px}.registration-card h2{margin:0;font-size:1.08rem}.registration-card>p{margin:6px 0 18px;color:var(--muted);font-size:.86rem;line-height:1.5}.registration-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}.registration-grid label{display:grid;gap:7px;color:var(--ink);font-size:.8rem;font-weight:800}.registration-grid input,.registration-grid select,.registration-grid textarea{width:100%;min-width:0;padding:10px 11px;border:1px solid var(--line);border-radius:9px;background:#fff}.registration-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}.registration-button{display:inline-flex;align-items:center;justify-content:center;padding:11px 15px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);font-size:.85rem;font-weight:800;text-decoration:none;cursor:pointer}.registration-button.primary{border-color:var(--blue);background:var(--blue);color:#fff}@media(max-width:700px){.registration-page{padding:24px 18px}.registration-heading{flex-direction:column}.registration-grid{grid-template-columns:1fr}.registration-actions{justify-content:stretch}.registration-actions>*{flex:1}}
</style>
@endpush

@section('content')
<section class="registration-page">
    <div class="registration-heading">
        <div>
            <p class="registration-eyebrow">RECEPTION & PATIENT RECORDS</p>
            <h1>Register a patient</h1>
            <p>Create the clinical identity and medical record first. Portal access is deliberately managed as a separate, auditable step.</p>
        </div>
        <a class="registration-back" href="{{ route('patients.index') }}">Back to registry</a>
    </div>

    @if ($errors->any())
        <div class="error" style="margin-bottom:18px">
            <strong>Please review the registration details.</strong>
            <ul style="margin:8px 0 0;padding-left:20px">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('patients.store') }}">
        @csrf
        <input type="hidden" name="facility_id" value="{{ $facility->id }}">
        <input type="hidden" name="country" value="{{ old('country', $facility->country ?: 'Uganda') }}">

        <section class="card registration-card">
            <h2>Identity</h2>
            <p>Core details establish the patient record and its unique medical record number.</p>
            <div class="registration-grid">
                <label>First name<input name="first_name" value="{{ old('first_name') }}" required autocomplete="given-name"></label>
                <label>Middle name<input name="middle_name" value="{{ old('middle_name') }}" autocomplete="additional-name"></label>
                <label>Last name<input name="last_name" value="{{ old('last_name') }}" required autocomplete="family-name"></label>
                <label>Sex<select name="sex"><option value="">Select</option><option value="female" @selected(old('sex') === 'female')>Female</option><option value="male" @selected(old('sex') === 'male')>Male</option><option value="other" @selected(old('sex') === 'other')>Other</option></select></label>
                <label>Date of birth<input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" autocomplete="bday"></label>
                <label>National ID<input name="national_id" value="{{ old('national_id') }}"></label>
                <label>Phone<input name="phone" value="{{ old('phone') }}" autocomplete="tel"></label>
                <label>Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="email"></label>
            </div>
        </section>

        <section class="card registration-card">
            <h2>Address and care contacts</h2>
            <p>Record enough contact information for safe communication and continuity of care.</p>
            <div class="registration-grid">
                <label>Address line 1<input name="address_line1" value="{{ old('address_line1') }}" autocomplete="address-line1"></label>
                <label>Address line 2<input name="address_line2" value="{{ old('address_line2') }}" autocomplete="address-line2"></label>
                <label>City<input name="city" value="{{ old('city') }}" autocomplete="address-level2"></label>
                <label>District<input name="district" value="{{ old('district') }}" autocomplete="address-level1"></label>
                <label>Emergency contact name<input name="emergency_contact_name" value="{{ old('emergency_contact_name') }}"></label>
                <label>Emergency contact relationship<input name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship') }}"></label>
                <label>Emergency contact phone<input name="emergency_contact_phone" value="{{ old('emergency_contact_phone') }}"></label>
                <label>Next of kin name<input name="next_of_kin_name" value="{{ old('next_of_kin_name') }}"></label>
                <label>Next of kin relationship<input name="next_of_kin_relationship" value="{{ old('next_of_kin_relationship') }}"></label>
                <label>Next of kin phone<input name="next_of_kin_phone" value="{{ old('next_of_kin_phone') }}"></label>
            </div>
        </section>

        <div class="registration-actions">
            <a class="registration-button" href="{{ route('patients.index') }}">Cancel</a>
            <button class="registration-button primary" type="submit">Create patient record</button>
        </div>
    </form>
</section>
@endsection
