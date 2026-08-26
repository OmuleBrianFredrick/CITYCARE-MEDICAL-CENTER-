<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\BillableService;
use App\Models\ClinicalEncounter;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Invoice;
use App\Models\LaboratoryTest;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\ServicePrice;
use App\Models\StaffProfile;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\BillingService;
use App\Services\LaboratoryOrderService;
use App\Services\PatientNotificationService;
use Database\Seeders\CityCareAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientNotificationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_event_notifications_are_stored_once_per_event_key(): void
    {
        [$user, $appointment] = $this->patientAppointment(now()->addDay());
        $notifications = app(PatientNotificationService::class);

        $first = $notifications->appointmentScheduled($appointment);
        $second = $notifications->appointmentScheduled($appointment);

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second?->id);
        $this->assertCount(1, $user->notifications()->get());
        $this->assertCount(1, $user->unreadNotifications()->get());
        $this->assertSame('Appointment scheduled', $first->data['title']);
        $this->assertSame("appointment:{$appointment->id}:scheduled:{$appointment->scheduled_start->timestamp}", $first->data['event_key']);
        $this->assertSame($appointment->id, $first->data['context']['appointment_id']);
    }

    public function test_notification_is_skipped_when_patient_has_no_linked_portal_user(): void
    {
        [, $appointment] = $this->patientAppointment(now()->addDay(), false);

        $notification = app(PatientNotificationService::class)->appointmentScheduled($appointment);

        $this->assertNull($notification);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_appointment_reminder_command_is_idempotent_and_limits_the_window(): void
    {
        $this->travelTo(now()->startOfHour());
        [$eligibleUser, $eligible] = $this->patientAppointment(now()->addHours(12));
        [$laterUser] = $this->patientAppointment(now()->addHours(30));
        [$cancelledUser, $cancelled] = $this->patientAppointment(now()->addHours(8));
        $cancelled->update(['status' => Appointment::STATUS_CANCELLED, 'cancelled_at' => now()]);

        $this->artisan('citycare:send-appointment-reminders', ['--hours' => 24])
            ->assertSuccessful();
        $this->artisan('citycare:send-appointment-reminders', ['--hours' => 24])
            ->assertSuccessful();

        $this->assertCount(1, $eligibleUser->notifications()->get());
        $this->assertStringContainsString(':reminder:24h:', $eligibleUser->notifications()->firstOrFail()->data['event_key']);
        $this->assertCount(0, $laterUser->notifications()->get());
        $this->assertCount(0, $cancelledUser->notifications()->get());
        $this->assertSame($eligible->id, $eligibleUser->notifications()->firstOrFail()->data['context']['appointment_id']);
    }

    public function test_appointment_reminder_setting_can_disable_delivery(): void
    {
        [$user] = $this->patientAppointment(now()->addHours(4));
        SystemSetting::create([
            'key' => 'appointments.reminders.enabled',
            'value' => '0',
            'type' => 'boolean',
            'group' => 'notifications',
            'is_public' => false,
        ]);

        $this->artisan('citycare:send-appointment-reminders')->assertSuccessful();

        $this->assertCount(0, $user->notifications()->get());
    }

    public function test_reminder_command_rejects_an_unsafe_window(): void
    {
        $this->artisan('citycare:send-appointment-reminders', ['--hours' => 0])
            ->assertFailed();
        $this->artisan('citycare:send-appointment-reminders', ['--hours' => 169])
            ->assertFailed();
    }

    public function test_patient_can_mark_only_their_own_notifications_as_read(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$user, $appointment] = $this->patientAppointment(now()->addDay());
        [$otherUser, $otherAppointment] = $this->patientAppointment(now()->addDays(2));
        $patientRole = Role::query()->where('slug', 'patient')->firstOrFail();
        $user->roles()->attach($patientRole);
        $otherUser->roles()->attach($patientRole);

        $notifications = app(PatientNotificationService::class);
        $own = $notifications->appointmentScheduled($appointment);
        $other = $notifications->appointmentScheduled($otherAppointment);

        $this->actingAs($user)
            ->post(route('portal.notifications.read', $other->id))
            ->assertNotFound();
        $this->assertNull($other->fresh()->read_at);

        $this->actingAs($user)
            ->post(route('portal.notifications.read', $own->id))
            ->assertRedirect()
            ->assertSessionHas('status', 'Notification marked as read.');
        $this->assertNotNull($own->fresh()->read_at);

        $second = $notifications->notify(
            $appointment->patient,
            'patient-message:follow-up',
            'Follow-up available',
            'A care-team follow-up is available.',
            '/portal',
        );
        $this->assertNull($second->read_at);

        $this->actingAs($user)
            ->post(route('portal.notifications.read-all'))
            ->assertRedirect()
            ->assertSessionHas('status', 'All notifications marked as read.');
        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(1, $otherUser->unreadNotifications()->count());
    }

    public function test_appointment_http_workflow_notifies_the_linked_patient(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$portalUser, , $patient] = $this->patientAppointment(now()->addDays(3));
        $facility = $patient->facility;
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);
        $receptionist = $this->staffWithRole('receptionist', $department);
        $start = now()->addDays(4)->startOfHour();

        $this->actingAs($receptionist)->post(route('appointments.store'), [
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'scheduled_start' => $start->toDateTimeString(),
            'scheduled_end' => $start->copy()->addMinutes(30)->toDateTimeString(),
            'reason' => 'Portal notification check',
        ])->assertRedirect(route('appointments.index'));

        $scheduled = Appointment::query()->where('patient_id', $patient->id)->latest('id')->firstOrFail();
        $this->assertTrue($portalUser->notifications()->get()->contains(
            fn ($notification) => $notification->data['title'] === 'Appointment scheduled',
        ));

        $this->actingAs($receptionist)
            ->post(route('appointments.cancel', $scheduled))
            ->assertRedirect();

        $this->assertCount(2, $portalUser->notifications()->get());
        $this->assertTrue($portalUser->notifications()->get()->contains(
            fn ($notification) => $notification->data['title'] === 'Appointment cancelled',
        ));
    }

    public function test_laboratory_result_http_workflow_notifies_the_linked_patient(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$portalUser, $appointment, $patient] = $this->patientAppointment(now()->addDay());
        $department = $appointment->department;
        $laboratoryStaff = $this->staffWithRole('laboratory', $department);
        $encounter = ClinicalEncounter::create([
            'facility_id' => $patient->facility_id,
            'department_id' => $department->id,
            'service_point_id' => $appointment->service_point_id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'clinician_id' => $laboratoryStaff->id,
            'encounter_number' => 'ENC-NOTIFY-'.str()->upper(str()->random(8)),
            'type' => ClinicalEncounter::TYPE_OUTPATIENT,
            'status' => ClinicalEncounter::STATUS_OPEN,
            'started_at' => now(),
        ]);
        $test = LaboratoryTest::factory()->create([
            'facility_id' => $patient->facility_id,
            'name' => 'Complete blood count',
            'is_active' => true,
        ]);
        $order = app(LaboratoryOrderService::class)->create($encounter, $laboratoryStaff, [
            'test_ids' => [$test->id],
        ]);

        $this->actingAs($laboratoryStaff)
            ->post(route('encounters.laboratory-order-items.result.store', $order->items->first()), [
                'result_value' => 'Within expected range',
            ])
            ->assertRedirect();

        $notification = $portalUser->notifications()->firstOrFail();
        $this->assertSame('Laboratory result ready', $notification->data['title']);
        $this->assertSame($order->id, $notification->data['context']['laboratory_order_id']);
    }

    public function test_billing_http_workflow_notifies_invoice_payment_reversal_and_cancellation(): void
    {
        $this->seed(CityCareAccessSeeder::class);
        [$portalUser, $appointment, $patient] = $this->patientAppointment(now()->addDay());
        $cashier = $this->staffWithRole('cashier', $appointment->department);
        $service = BillableService::factory()->create([
            'facility_id' => $patient->facility_id,
            'is_active' => true,
        ]);
        $price = ServicePrice::factory()->create([
            'facility_id' => $patient->facility_id,
            'billable_service_id' => $service->id,
            'amount' => 1000,
            'currency' => 'UGX',
            'effective_from' => now()->subDay(),
            'effective_to' => null,
            'is_active' => true,
        ]);
        $charge = app(BillingService::class)->addCharge($cashier, $patient, $service, $price);

        $this->actingAs($cashier)
            ->post(route('billing.invoices.store', $patient), ['charges' => [$charge->id]])
            ->assertRedirect();
        $invoice = Invoice::query()->where('patient_id', $patient->id)->latest('id')->firstOrFail();
        $this->assertTrue($portalUser->notifications()->get()->contains(
            fn ($notification) => $notification->data['title'] === 'New invoice issued',
        ));

        $this->actingAs($cashier)
            ->post(route('billing.payments.store', $invoice), ['amount' => 1000, 'method' => Payment::METHOD_CASH])
            ->assertRedirect();
        $payment = Payment::query()->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertTrue($portalUser->notifications()->get()->contains(
            fn ($notification) => $notification->data['title'] === 'Payment received',
        ));

        $this->actingAs($cashier)
            ->post(route('billing.payments.reverse', $payment), ['action' => 'refund', 'reason' => 'Patient refund'])
            ->assertRedirect();
        $this->assertTrue($portalUser->notifications()->get()->contains(
            fn ($notification) => $notification->data['title'] === 'Payment refunded',
        ));

        $this->actingAs($cashier)
            ->post(route('billing.invoices.cancel', $invoice), ['reason' => 'Invoice withdrawn'])
            ->assertRedirect();
        $this->assertTrue($portalUser->notifications()->get()->contains(
            fn ($notification) => $notification->data['title'] === 'Invoice cancelled',
        ));
        $this->assertCount(4, $portalUser->notifications()->get());
    }

    private function patientAppointment(\DateTimeInterface $start, bool $linked = true): array
    {
        $facility = Facility::factory()->create();
        $department = Department::factory()->create(['facility_id' => $facility->id]);
        $servicePoint = ServicePoint::factory()->create(['department_id' => $department->id]);
        $user = $linked
            ? User::factory()->create(['user_type' => 'patient', 'is_active' => true])
            : null;
        $patient = Patient::factory()->create([
            'facility_id' => $facility->id,
            'user_id' => $user?->id,
            'portal_activated_at' => $user ? now() : null,
            'portal_disabled_at' => null,
        ]);
        $appointment = Appointment::create([
            'facility_id' => $facility->id,
            'department_id' => $department->id,
            'service_point_id' => $servicePoint->id,
            'patient_id' => $patient->id,
            'appointment_number' => 'APT-NOTIFY-'.str()->upper(str()->random(10)),
            'scheduled_start' => $start,
            'scheduled_end' => (clone $start)->modify('+30 minutes'),
            'status' => Appointment::STATUS_SCHEDULED,
        ]);

        return [$user, $appointment, $patient];
    }

    private function staffWithRole(string $roleSlug, Department $department): User
    {
        $staff = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $staff->roles()->attach(Role::query()->where('slug', $roleSlug)->firstOrFail());
        StaffProfile::create([
            'user_id' => $staff->id,
            'department_id' => $department->id,
        ]);

        return $staff;
    }
}
