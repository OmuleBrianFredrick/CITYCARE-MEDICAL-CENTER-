<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalNote;
use App\Models\ClinicalReferral;
use App\Models\ClinicalReferralAttachment;
use App\Models\ClinicalTreatmentPlan;
use App\Models\Department;
use App\Models\Facility;
use App\Models\Patient;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClinicalFacilityAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_clinical_worklists_and_every_record_mutation_are_facility_scoped(): void
    {
        $ownFacility = Facility::factory()->create(['is_active' => true]);
        $ownDepartment = Department::factory()->create(['facility_id' => $ownFacility->id]);
        $doctor = User::factory()->create(['user_type' => 'staff', 'is_active' => true]);
        $doctor->roles()->attach(Role::where('slug', 'doctor')->valueOrFail('id'));
        StaffProfile::create([
            'user_id' => $doctor->id,
            'department_id' => $ownDepartment->id,
            'employee_number' => 'SEC-DOCTOR',
            'employment_status' => 'active',
        ]);

        $foreignFacility = Facility::factory()->create(['is_active' => true]);
        $foreignDepartment = Department::factory()->create(['facility_id' => $foreignFacility->id]);
        $foreignServicePoint = ServicePoint::factory()->create(['department_id' => $foreignDepartment->id]);
        $foreignPatient = Patient::factory()->create([
            'facility_id' => $foreignFacility->id,
            'first_name' => 'Foreign',
            'last_name' => 'Clinical Patient',
            'status' => Patient::STATUS_ACTIVE,
        ]);
        $foreignAppointment = Appointment::factory()->create([
            'facility_id' => $foreignFacility->id,
            'department_id' => $foreignDepartment->id,
            'service_point_id' => $foreignServicePoint->id,
            'patient_id' => $foreignPatient->id,
            'provider_id' => $doctor->id,
            'status' => Appointment::STATUS_CHECKED_IN,
            'checked_in_at' => now(),
        ]);
        $foreignEncounter = ClinicalEncounter::factory()->create([
            'facility_id' => $foreignFacility->id,
            'department_id' => $foreignDepartment->id,
            'service_point_id' => $foreignServicePoint->id,
            'patient_id' => $foreignPatient->id,
            'appointment_id' => null,
            'clinician_id' => $doctor->id,
            'encounter_number' => 'ENC-FOREIGN-SECURITY',
            'status' => ClinicalEncounter::STATUS_OPEN,
        ]);
        $note = ClinicalNote::factory()->create([
            'encounter_id' => $foreignEncounter->id,
            'author_id' => $doctor->id,
        ]);
        $plan = ClinicalTreatmentPlan::factory()->create([
            'encounter_id' => $foreignEncounter->id,
            'author_id' => $doctor->id,
        ]);
        $referral = ClinicalReferral::factory()->create([
            'encounter_id' => $foreignEncounter->id,
            'referrer_id' => $doctor->id,
        ]);
        $attachment = ClinicalReferralAttachment::factory()->create([
            'clinical_referral_id' => $referral->id,
            'uploaded_by' => $doctor->id,
        ]);

        $this->actingAs($doctor)
            ->get(route('encounters.index', ['search' => $foreignEncounter->encounter_number]))
            ->assertOk()
            ->assertDontSee($foreignPatient->full_name);
        $this->actingAs($doctor)
            ->get(route('encounters.create'))
            ->assertOk()
            ->assertDontSee($foreignAppointment->appointment_number);
        $this->actingAs($doctor)->get(route('encounters.show', $foreignEncounter))->assertForbidden();

        $this->actingAs($doctor)->post(route('encounters.store'), [
            'appointment_id' => $foreignAppointment->id,
            'type' => ClinicalEncounter::TYPE_OUTPATIENT,
        ])->assertForbidden();
        $this->actingAs($doctor)->post(route('encounters.close', $foreignEncounter), [
            'summary' => 'Forged closure',
        ])->assertForbidden();
        $this->actingAs($doctor)->post(route('encounters.cancel', $foreignEncounter))->assertForbidden();
        $this->actingAs($doctor)->post(route('encounters.vitals.store', $foreignEncounter), [
            'pulse_bpm' => 70,
        ])->assertForbidden();
        $this->actingAs($doctor)->post(route('encounters.notes.store', $foreignEncounter), [
            'chief_complaint' => 'Forged note',
        ])->assertForbidden();
        $this->actingAs($doctor)->post(route('encounters.notes.finalize', $note))->assertForbidden();
        $this->actingAs($doctor)->post(route('encounters.diagnoses.store', $foreignEncounter), [
            'diagnosis' => 'Forged diagnosis',
            'type' => 'primary',
        ])->assertForbidden();
        $this->actingAs($doctor)->post(route('encounters.treatment-plans.store', $foreignEncounter), [
            'plan' => 'Forged treatment plan',
        ])->assertForbidden();
        $this->actingAs($doctor)->post(route('encounters.treatment-plans.complete', $plan))->assertForbidden();
        $this->actingAs($doctor)->post(route('encounters.treatment-plans.cancel', $plan))->assertForbidden();
        $this->actingAs($doctor)->post(route('encounters.referrals.store', $foreignEncounter), [
            'referred_to' => 'Forged service',
            'reason' => 'Forged referral',
        ])->assertForbidden();
        $this->actingAs($doctor)->post(route('encounters.referrals.accept', $referral))->assertForbidden();
        $this->actingAs($doctor)->post(route('encounters.referrals.complete', $referral))->assertForbidden();
        $this->actingAs($doctor)->post(route('encounters.referrals.cancel', $referral))->assertForbidden();
        $this->actingAs($doctor)->delete(route('encounters.referrals.attachments.destroy', $attachment))->assertForbidden();

        $this->assertSame(ClinicalEncounter::STATUS_OPEN, $foreignEncounter->fresh()->status);
        $this->assertNull($note->fresh()->finalized_at);
        $this->assertSame(ClinicalTreatmentPlan::STATUS_ACTIVE, $plan->fresh()->status);
        $this->assertSame(ClinicalReferral::STATUS_PENDING, $referral->fresh()->status);
        $this->assertDatabaseHas('clinical_referral_attachments', ['id' => $attachment->id]);
        $this->assertDatabaseMissing('clinical_vitals', ['encounter_id' => $foreignEncounter->id, 'recorded_by' => $doctor->id]);
        $this->assertDatabaseMissing('clinical_diagnoses', ['encounter_id' => $foreignEncounter->id, 'diagnosis' => 'Forged diagnosis']);
    }
}
