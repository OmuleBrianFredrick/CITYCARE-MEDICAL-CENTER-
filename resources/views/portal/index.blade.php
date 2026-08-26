@extends('layouts.app')

@section('title', 'My health · CityCare Medical Center')

@push('styles')
<style>
    .patient-page{padding:clamp(24px,4vw,46px)}.patient-hero{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(260px,.65fr);gap:18px;margin-bottom:24px}.patient-welcome{padding:clamp(24px,4vw,38px);border:0;background:linear-gradient(135deg,#082f49,#075985);color:#fff}.patient-eyebrow{margin:0 0 8px;color:#bae6fd;font-size:.71rem;font-weight:850;letter-spacing:.14em}.patient-welcome h1{margin:0;font-size:clamp(2rem,4vw,3rem);letter-spacing:-.05em}.patient-welcome>p:last-child{max-width:680px;margin:11px 0 0;color:#d8effa;line-height:1.58}.balance-card{display:flex;flex-direction:column;justify-content:center;padding:24px}.balance-card small{color:var(--muted);font-weight:750}.balance-card strong{display:block;margin:7px 0;font-size:clamp(1.65rem,4vw,2.3rem);letter-spacing:-.04em}.balance-card span{color:var(--muted);font-size:.82rem}.patient-section{margin-bottom:20px;padding:22px}.section-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:18px}.section-head h2{margin:0;font-size:1.16rem}.section-head p{margin:5px 0 0;color:var(--muted);font-size:.84rem}.record-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.record{padding:16px;border:1px solid var(--line);border-radius:13px;background:#fff}.record-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.record h3{margin:0;font-size:.94rem}.record-meta{margin:5px 0 0;color:var(--muted);font-size:.78rem;line-height:1.45}.record-detail{margin:12px 0 0;padding-top:12px;border-top:1px solid #edf2f7;font-size:.84rem;line-height:1.55}.badge{display:inline-flex;padding:5px 8px;border-radius:999px;background:#e0f2fe;color:#075985;font-size:.67rem;font-weight:850;text-transform:capitalize}.badge.green{background:#ecfdf3;color:var(--green)}.badge.red{background:#fef2f2;color:var(--red)}.badge.amber{background:#fff7ed;color:#9a3412}.empty{padding:24px;border:1px dashed var(--line);border-radius:13px;background:#f8fafc;color:var(--muted);text-align:center;font-size:.86rem}.profile-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.profile-item small{display:block;color:var(--muted);font-size:.72rem;font-weight:750}.profile-item strong{display:block;margin-top:5px;font-size:.86rem}.result-row,.medicine-row,.invoice-line,.receipt-row{display:flex;justify-content:space-between;gap:14px;margin-top:9px;padding-top:9px;border-top:1px solid #edf2f7;font-size:.82rem}.result-value,.money{font-weight:850;text-align:right}.abnormal{color:var(--red)}.muted{color:var(--muted)}.diagnoses{margin:10px 0 0;padding-left:18px;font-size:.82rem}.diagnoses li+li{margin-top:4px}.section-action{border:0;border-radius:9px;padding:9px 12px;background:#e0f2fe;color:#075985;font-size:.76rem;font-weight:850;cursor:pointer}.notification-list{display:grid;gap:10px}.notification{display:flex;justify-content:space-between;gap:16px;padding:14px;border:1px solid var(--line);border-radius:12px;background:#fff}.notification.unread{border-color:#93c5fd;background:#eff6ff}.notification h3{margin:0;font-size:.88rem}.notification p{margin:5px 0 0;color:var(--muted);font-size:.8rem;line-height:1.5}.notification-meta{display:flex;align-items:center;gap:9px;white-space:nowrap}.notification-meta a{font-size:.76rem;font-weight:800}.notification-meta button{border:0;background:transparent;color:var(--blue);font-size:.75rem;font-weight:850;cursor:pointer}@media(max-width:900px){.patient-hero{grid-template-columns:1fr}.profile-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:680px){.patient-page{padding:22px 16px}.record-grid,.profile-grid{grid-template-columns:1fr}.section-head,.record-top,.notification{flex-direction:column}.result-row,.medicine-row,.invoice-line,.receipt-row{align-items:flex-start}.balance-card{min-height:150px}.notification-meta{white-space:normal}}
</style>
@endpush

@section('content')
<section class="patient-page">
    @if (session('status'))<div class="status" style="margin-bottom:18px">{{ session('status') }}</div>@endif
    <div class="patient-hero">
        <section class="card patient-welcome">
            <p class="patient-eyebrow">MY CITYCARE HEALTH</p>
            <h1>Welcome, {{ $patient->first_name }}</h1>
            <p>Review your upcoming care, finalized results, prescribed medicines, visit summaries, and billing records. Contact CityCare if anything appears incorrect.</p>
        </section>
        <section class="card balance-card">
            <small>Current outstanding balance</small>
            <strong>{{ $patient->facility?->currency ?? 'UGX' }} {{ number_format($outstandingBalance, 2) }}</strong>
            <span>Across issued invoices shown in your portal.</span>
        </section>
    </div>

    <section class="card patient-section">
        <div class="section-head"><div><h2>My profile</h2><p>Your key registration details.</p></div></div>
        <div class="profile-grid">
            <div class="profile-item"><small>Medical record number</small><strong>{{ $patient->medical_record_number }}</strong></div>
            <div class="profile-item"><small>Date of birth</small><strong>{{ $patient->date_of_birth?->format('d M Y') ?? 'Not recorded' }}</strong></div>
            <div class="profile-item"><small>Phone</small><strong>{{ $patient->phone ?: 'Not recorded' }}</strong></div>
            <div class="profile-item"><small>Email</small><strong>{{ $patient->email ?: auth()->user()->email }}</strong></div>
        </div>
    </section>

    <section class="card patient-section" id="notifications">
        <div class="section-head">
            <div><h2>Updates @if($unreadNotificationCount > 0)<span class="badge">{{ $unreadNotificationCount }} new</span>@endif</h2><p>Important changes to your appointments, results, and account.</p></div>
            @if($unreadNotificationCount > 0)<form method="POST" action="{{ route('portal.notifications.read-all') }}">@csrf<button class="section-action" type="submit">Mark all as read</button></form>@endif
        </div>
        @if($notifications->isEmpty())
            <div class="empty">You have no portal updates yet.</div>
        @else
            <div class="notification-list">
                @foreach($notifications as $notification)
                    <article class="notification @if(is_null($notification->read_at)) unread @endif">
                        <div><h3>{{ $notification->data['title'] ?? 'CityCare update' }}</h3><p>{{ $notification->data['message'] ?? '' }}</p></div>
                        <div class="notification-meta">
                            @if(!empty($notification->data['url']))<a href="{{ $notification->data['url'] }}">View</a>@endif
                            @if(is_null($notification->read_at))<form method="POST" action="{{ route('portal.notifications.read', $notification->id) }}">@csrf<button type="submit">Mark read</button></form>@endif
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="card patient-section" id="appointments">
        <div class="section-head"><div><h2>Upcoming appointments</h2><p>Scheduled visits and where to attend.</p></div></div>
        @if ($upcomingAppointments->isEmpty())
            <div class="empty">You have no upcoming appointments.</div>
        @else
            <div class="record-grid">
                @foreach ($upcomingAppointments as $appointment)
                    <article class="record">
                        <div class="record-top"><div><h3>{{ $appointment->scheduled_start->format('D, d M Y · H:i') }}</h3><p class="record-meta">{{ $appointment->department?->name ?? 'CityCare clinic' }}{{ $appointment->servicePoint?->name ? ' · '.$appointment->servicePoint->name : '' }}</p></div><span class="badge">Scheduled</span></div>
                        <p class="record-detail"><strong>Reason:</strong> {{ $appointment->reason ?: 'Routine appointment' }}@if($appointment->provider)<br><span class="muted">Provider: {{ $appointment->provider->name }}</span>@endif</p>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="card patient-section" id="laboratory-results">
        <div class="section-head"><div><h2>Laboratory results</h2><p>Results recorded by the CityCare laboratory team.</p></div></div>
        @if ($laboratoryOrders->isEmpty())
            <div class="empty">No finalized laboratory results are available yet.</div>
        @else
            <div class="record-grid">
                @foreach ($laboratoryOrders as $order)
                    <article class="record">
                        <div class="record-top"><div><h3>{{ $order->order_number }}</h3><p class="record-meta">Ordered {{ $order->ordered_at->format('d M Y') }}</p></div><span class="badge green">Available</span></div>
                        @foreach ($order->items as $item)
                            <div class="result-row"><span><strong>{{ $item->laboratoryTest?->name ?? 'Laboratory test' }}</strong><br><span class="muted">Reference: {{ $item->result?->reference_range ?: $item->laboratoryTest?->reference_range ?: 'Not specified' }}</span></span><span class="result-value @if($item->result?->is_abnormal) abnormal @endif">{{ $item->result?->result_value ?? '—' }} {{ $item->result?->unit }}</span></div>
                        @endforeach
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="card patient-section" id="medications">
        <div class="section-head"><div><h2>Medicines</h2><p>Current and recently completed prescriptions.</p></div></div>
        @if ($prescriptions->isEmpty())
            <div class="empty">No prescriptions are available.</div>
        @else
            <div class="record-grid">
                @foreach ($prescriptions as $prescription)
                    <article class="record">
                        <div class="record-top"><div><h3>{{ $prescription->prescription_number }}</h3><p class="record-meta">Prescribed {{ $prescription->prescribed_at?->format('d M Y') ?? '—' }}{{ $prescription->prescriber ? ' by '.$prescription->prescriber->name : '' }}</p></div><span class="badge @if($prescription->isCompleted()) green @endif">{{ str_replace('_', ' ', $prescription->status) }}</span></div>
                        @foreach ($prescription->items as $item)
                            <div class="medicine-row"><span><strong>{{ $item->medication?->name ?? 'Medicine' }}</strong><br><span class="muted">{{ $item->formulation?->strength }} {{ $item->formulation?->unit }}</span></span><span class="result-value">{{ collect([$item->dose, $item->frequency, $item->duration])->filter()->join(' · ') }}</span></div>
                            @if($item->instructions)<p class="record-meta">Instructions: {{ $item->instructions }}</p>@endif
                        @endforeach
                        @if($prescription->dispensings->isNotEmpty())<p class="record-detail">Last dispensed {{ $prescription->dispensings->first()->dispensed_at?->format('d M Y · H:i') }}</p>@endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="card patient-section" id="care-history">
        <div class="section-head"><div><h2>Care history</h2><p>Completed visit summaries and recorded diagnoses.</p></div></div>
        @if ($careHistory->isEmpty())
            <div class="empty">No completed visit summaries are available.</div>
        @else
            <div class="record-grid">
                @foreach ($careHistory as $encounter)
                    <article class="record">
                        <div class="record-top"><div><h3>{{ $encounter->closed_at?->format('d M Y') ?? $encounter->started_at->format('d M Y') }}</h3><p class="record-meta">{{ $encounter->department?->name ?? 'Clinical visit' }}{{ $encounter->clinician ? ' · '.$encounter->clinician->name : '' }}</p></div><span class="badge green">Completed</span></div>
                        <p class="record-detail">{{ $encounter->summary ?: 'Visit completed. Contact CityCare for more information.' }}</p>
                        @if($encounter->diagnoses->isNotEmpty())<ul class="diagnoses">@foreach($encounter->diagnoses as $diagnosis)<li>{{ $diagnosis->diagnosis }}</li>@endforeach</ul>@endif
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="card patient-section" id="billing">
        <div class="section-head"><div><h2>Invoices and receipts</h2><p>Issued bills, balances, and completed payment receipts.</p></div></div>
        @if ($invoices->isEmpty())
            <div class="empty">No issued invoices are available.</div>
        @else
            <div class="record-grid">
                @foreach ($invoices as $invoice)
                    <article class="record">
                        <div class="record-top"><div><h3>{{ $invoice->invoice_number }}</h3><p class="record-meta">Issued {{ $invoice->issued_at?->format('d M Y') ?? '—' }}</p></div><span class="badge @if($invoice->isPaid()) green @elseif($invoice->isPartiallyPaid()) amber @elseif($invoice->isCancelled()) red @endif">{{ str_replace('_', ' ', $invoice->status) }}</span></div>
                        @foreach ($invoice->lineItems as $line)<div class="invoice-line"><span>{{ $line->description }} × {{ rtrim(rtrim($line->quantity, '0'), '.') }}</span><span class="money">{{ $line->currency }} {{ number_format((float) $line->line_total, 2) }}</span></div>@endforeach
                        <div class="invoice-line"><strong>Balance due</strong><strong class="money">{{ $invoice->currency }} {{ number_format((float) $invoice->balance_due, 2) }}</strong></div>
                        @foreach ($invoice->payments as $payment)<div class="receipt-row"><span>Receipt {{ $payment->receipt_number }}<br><span class="muted">{{ str_replace('_', ' ', $payment->method) }} · {{ $payment->paid_at?->format('d M Y') }} · {{ $payment->status }}</span></span><span class="money @if(in_array($payment->status, ['voided', 'refunded'], true)) abnormal @endif">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</span></div>@endforeach
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="card patient-section" id="appointment-history">
        <div class="section-head"><div><h2>Appointment history</h2><p>Recent completed, checked-in, cancelled, and past appointments.</p></div></div>
        @if ($appointmentHistory->isEmpty())
            <div class="empty">No earlier appointments are available.</div>
        @else
            <div class="record-grid">
                @foreach ($appointmentHistory as $appointment)
                    <article class="record"><div class="record-top"><div><h3>{{ $appointment->scheduled_start->format('d M Y · H:i') }}</h3><p class="record-meta">{{ $appointment->department?->name ?? 'CityCare clinic' }} · {{ $appointment->reason ?: 'Appointment' }}</p></div><span class="badge @if($appointment->status === 'cancelled') red @elseif($appointment->status === 'completed') green @endif">{{ str_replace('_', ' ', $appointment->status) }}</span></div></article>
                @endforeach
            </div>
        @endif
    </section>
</section>
@endsection
