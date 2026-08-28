<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\AuditEvent;
use App\Models\BillableService;
use App\Models\ClinicalDiagnosis;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalNote;
use App\Models\ClinicalReferral;
use App\Models\ClinicalTreatmentPlan;
use App\Models\ClinicalVital;
use App\Models\Department;
use App\Models\Facility;
use App\Models\InventoryItem;
use App\Models\InventoryStore;
use App\Models\InventorySupplier;
use App\Models\Invoice;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryOrderItem;
use App\Models\LaboratoryResult;
use App\Models\LaboratoryTest;
use App\Models\Medication;
use App\Models\MedicationFormulation;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\ReportDefinition;
use App\Models\ServicePoint;
use App\Models\ServicePrice;
use App\Models\User;
use App\Services\BillingService;
use App\Services\InventoryProcurementService;
use App\Services\PharmacyInventoryDispensingService;
use App\Services\PharmacyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CityCareDemoDataSeeder extends Seeder
{
    private const MARKER = 'CITYCARE_DEMO';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw ValidationException::withMessages([
                'environment' => 'CityCare demonstration data may only be seeded in local or testing environments.',
            ]);
        }

        $this->call([
            CityCareAccessSeeder::class,
            CityCareOrganizationSeeder::class,
            CityCareDemoAccountSeeder::class,
        ]);

        $facility = Facility::query()->where('is_active', true)->orderBy('id')->firstOrFail();
        $accounts = $this->accounts();
        $departments = Department::query()->where('facility_id', $facility->id)->get()->keyBy('code');
        $servicePoints = ServicePoint::query()->get()->keyBy('code');
        $patients = $this->seedPatients($facility, $accounts);
        $appointments = $this->seedAppointments($facility, $departments, $servicePoints, $patients, $accounts);
        $encounters = $this->seedEncounters($facility, $departments, $servicePoints, $patients, $appointments, $accounts);

        $this->seedClinicalRecords($encounters, $accounts);
        $tests = $this->seedLaboratoryCatalog($facility);
        $this->seedLaboratoryWork($facility, $encounters, $patients, $tests, $accounts);
        [$medications, $formulations] = $this->seedMedicationCatalog($facility);
        [$pharmacyStore, $inventoryItems] = $this->seedInventoryCatalog($facility, $servicePoints);
        $this->seedProcurement($facility, $pharmacyStore, $inventoryItems, $accounts['inventory@citycare.test']);
        $this->seedPrescriptions($encounters, $medications, $formulations, $pharmacyStore, $accounts);
        $this->seedBilling($facility, $patients, $encounters, $accounts);
        $this->seedReportDefinitions();
        $this->seedAuditEvents($facility, $patients, $encounters, $accounts);
    }

    private function accounts(): Collection
    {
        return User::query()->whereIn('email', [
            'admin@citycare.test', 'administrator@citycare.test', 'reception@citycare.test',
            'doctor@citycare.test', 'nurse@citycare.test', 'laboratory@citycare.test',
            'pharmacy@citycare.test', 'cashier@citycare.test', 'records@citycare.test',
            'inventory@citycare.test', 'patient@citycare.test',
        ])->get()->keyBy('email');
    }

    private function seedPatients(Facility $facility, Collection $accounts): array
    {
        $now = now();
        $definitions = [
            'portal' => ['medical_record_number' => 'CC-DEMO-0001', 'user_id' => $accounts['patient@citycare.test']->id, 'first_name' => 'Amina', 'middle_name' => 'N.', 'last_name' => 'Nakato', 'sex' => 'female', 'date_of_birth' => '1993-06-18', 'phone' => '+256700000001', 'email' => 'patient@citycare.test', 'city' => 'Kampala', 'district' => 'Kampala', 'emergency_contact_name' => 'Musa Nakato', 'emergency_contact_relationship' => 'Spouse', 'emergency_contact_phone' => '+256700000101', 'status' => Patient::STATUS_ACTIVE, 'registered_at' => $now->copy()->subDays(20), 'portal_invited_at' => $now->copy()->subDays(20), 'portal_activated_at' => $now->copy()->subDays(19), 'portal_disabled_at' => null],
            'scheduled' => ['medical_record_number' => 'CC-DEMO-0002', 'first_name' => 'Brian', 'last_name' => 'Okello', 'sex' => 'male', 'date_of_birth' => '1988-11-03', 'phone' => '+256700000002', 'email' => 'brian.okello@citycare.test', 'city' => 'Kampala', 'district' => 'Nakawa', 'status' => Patient::STATUS_ACTIVE, 'registered_at' => $now->copy()->subDays(14)],
            'history' => ['medical_record_number' => 'CC-DEMO-0003', 'first_name' => 'Sarah', 'last_name' => 'Nansubuga', 'sex' => 'female', 'date_of_birth' => '1979-02-12', 'phone' => '+256700000003', 'email' => 'sarah.nansubuga@citycare.test', 'city' => 'Kampala', 'district' => 'Makindye', 'status' => Patient::STATUS_ACTIVE, 'registered_at' => $now->copy()->subDays(40)],
            'follow_up' => ['medical_record_number' => 'CC-DEMO-0004', 'first_name' => 'Joseph', 'middle_name' => 'K.', 'last_name' => 'Mugisha', 'sex' => 'male', 'date_of_birth' => '2001-09-24', 'phone' => '+256700000004', 'email' => 'joseph.mugisha@citycare.test', 'city' => 'Kampala', 'district' => 'Rubaga', 'status' => Patient::STATUS_ACTIVE, 'registered_at' => $now->copy()->subDays(7)],
        ];

        $patients = [];
        foreach ($definitions as $key => $definition) {
            $medicalRecordNumber = $definition['medical_record_number'];
            unset($definition['medical_record_number']);
            $patients[$key] = Patient::updateOrCreate(
                ['medical_record_number' => $medicalRecordNumber],
                array_merge(['facility_id' => $facility->id, 'country' => 'Uganda'], $definition),
            );
        }

        return $patients;
    }

    private function seedAppointments(Facility $facility, Collection $departments, Collection $servicePoints, array $patients, Collection $accounts): array
    {
        $today = now()->startOfDay();
        $definitions = [
            'checked_in' => ['number' => 'DEMO-APT-CHECKED-IN', 'patient' => 'portal', 'start' => $today->copy()->setTime(8, 30), 'status' => Appointment::STATUS_CHECKED_IN, 'reason' => 'Persistent fever and headache.', 'checked_in_at' => now()->subMinutes(45)],
            'scheduled' => ['number' => 'DEMO-APT-SCHEDULED', 'patient' => 'scheduled', 'start' => $today->copy()->setTime(11, 0), 'status' => Appointment::STATUS_SCHEDULED, 'reason' => 'Routine outpatient consultation.'],
            'completed' => ['number' => 'DEMO-APT-COMPLETED', 'patient' => 'history', 'start' => $today->copy()->subDay()->setTime(9, 0), 'status' => Appointment::STATUS_COMPLETED, 'reason' => 'Follow-up after treatment.', 'checked_in_at' => $today->copy()->subDay()->setTime(8, 50), 'completed_at' => $today->copy()->subDay()->setTime(10, 15)],
            'follow_up' => ['number' => 'DEMO-APT-FOLLOW-UP', 'patient' => 'follow_up', 'start' => $today->copy()->addDay()->setTime(10, 30), 'status' => Appointment::STATUS_SCHEDULED, 'reason' => 'Planned follow-up visit.'],
        ];

        $appointments = [];
        foreach ($definitions as $key => $definition) {
            $start = $definition['start'];
            $appointments[$key] = Appointment::updateOrCreate(
                ['appointment_number' => $definition['number']],
                [
                    'facility_id' => $facility->id,
                    'department_id' => $departments['OPD']->id,
                    'service_point_id' => $servicePoints['OPD-GENERAL']->id,
                    'patient_id' => $patients[$definition['patient']]->id,
                    'provider_id' => $accounts['doctor@citycare.test']->id,
                    'scheduled_start' => $start,
                    'scheduled_end' => $start->copy()->addMinutes(30),
                    'status' => $definition['status'],
                    'reason' => $definition['reason'],
                    'notes' => self::MARKER.': reception workflow',
                    'checked_in_at' => $definition['checked_in_at'] ?? null,
                    'completed_at' => $definition['completed_at'] ?? null,
                    'cancelled_at' => null,
                    'created_by' => $accounts['reception@citycare.test']->id,
                ],
            );
        }

        return $appointments;
    }

    private function seedEncounters(Facility $facility, Collection $departments, Collection $servicePoints, array $patients, array $appointments, Collection $accounts): array
    {
        $definitions = [
            'active' => ['number' => 'DEMO-ENC-ACTIVE', 'patient' => 'portal', 'appointment' => 'checked_in', 'status' => ClinicalEncounter::STATUS_OPEN, 'started_at' => now()->subMinutes(40), 'closed_at' => null, 'summary' => 'Awaiting laboratory result and pharmacy dispensing.'],
            'closed' => ['number' => 'DEMO-ENC-CLOSED', 'patient' => 'history', 'appointment' => 'completed', 'status' => ClinicalEncounter::STATUS_CLOSED, 'started_at' => now()->subDay()->setTime(9, 5), 'closed_at' => now()->subDay()->setTime(10, 10), 'summary' => 'Historical visit completed with dispensing and partial payment.'],
        ];

        $encounters = [];
        foreach ($definitions as $key => $definition) {
            $encounters[$key] = ClinicalEncounter::updateOrCreate(
                ['encounter_number' => $definition['number']],
                [
                    'facility_id' => $facility->id,
                    'department_id' => $departments['OPD']->id,
                    'service_point_id' => $servicePoints['OPD-GENERAL']->id,
                    'patient_id' => $patients[$definition['patient']]->id,
                    'appointment_id' => $appointments[$definition['appointment']]->id,
                    'clinician_id' => $accounts['doctor@citycare.test']->id,
                    'type' => ClinicalEncounter::TYPE_OUTPATIENT,
                    'status' => $definition['status'],
                    'started_at' => $definition['started_at'],
                    'closed_at' => $definition['closed_at'],
                    'summary' => $definition['summary'],
                ],
            );
        }

        return $encounters;
    }

    private function seedClinicalRecords(array $encounters, Collection $accounts): void
    {
        $doctor = $accounts['doctor@citycare.test'];
        $nurse = $accounts['nurse@citycare.test'];

        ClinicalVital::query()->firstOrCreate(
            ['encounter_id' => $encounters['active']->id, 'recorded_by' => $nurse->id],
            ['temperature_c' => 38.2, 'pulse_bpm' => 98, 'respiratory_rate' => 18, 'oxygen_saturation' => 98, 'systolic_bp' => 118, 'diastolic_bp' => 76, 'weight_kg' => 61.5, 'height_cm' => 164, 'bmi' => 22.9, 'pain_score' => 4, 'notes' => self::MARKER.': triage completed'],
        );
        ClinicalVital::query()->firstOrCreate(
            ['encounter_id' => $encounters['closed']->id, 'recorded_by' => $nurse->id],
            ['temperature_c' => 36.8, 'pulse_bpm' => 76, 'respiratory_rate' => 16, 'oxygen_saturation' => 99, 'systolic_bp' => 122, 'diastolic_bp' => 78, 'weight_kg' => 70.2, 'height_cm' => 171, 'bmi' => 24, 'pain_score' => 1, 'notes' => self::MARKER.': historical triage'],
        );

        ClinicalNote::query()->firstOrCreate(
            ['encounter_id' => $encounters['active']->id, 'author_id' => $doctor->id],
            ['chief_complaint' => 'Fever, headache, and fatigue for two days.', 'history_of_present_illness' => 'Symptoms began gradually and have not improved with rest.', 'examination' => 'Alert, hydrated, and clinically stable.', 'assessment' => 'Febrile illness; malaria test requested.', 'diagnosis' => 'Working diagnosis pending laboratory result.', 'treatment_plan' => 'Supportive care and review result before treatment adjustment.', 'follow_up_plan' => 'Review today once the result is available.'],
        );
        ClinicalNote::query()->firstOrCreate(
            ['encounter_id' => $encounters['closed']->id, 'author_id' => $doctor->id],
            ['chief_complaint' => 'Improved respiratory symptoms.', 'assessment' => 'Responded well to prescribed treatment.', 'diagnosis' => 'Upper respiratory tract infection.', 'treatment_plan' => 'Complete medication and return if symptoms recur.', 'follow_up_plan' => 'Routine follow-up in two weeks.', 'finalized_at' => now()->subDay()->setTime(10, 5)],
        );

        ClinicalDiagnosis::query()->firstOrCreate(
            ['encounter_id' => $encounters['active']->id, 'recorded_by' => $doctor->id, 'diagnosis' => 'Acute febrile illness'],
            ['diagnosis_code' => 'R50.9', 'type' => 'primary', 'notes' => 'Awaiting laboratory confirmation.'],
        );
        ClinicalDiagnosis::query()->firstOrCreate(
            ['encounter_id' => $encounters['closed']->id, 'recorded_by' => $doctor->id, 'diagnosis' => 'Upper respiratory tract infection'],
            ['diagnosis_code' => 'J06.9', 'type' => 'primary', 'notes' => 'Resolved with outpatient treatment.'],
        );

        ClinicalTreatmentPlan::query()->firstOrCreate(
            ['encounter_id' => $encounters['active']->id, 'author_id' => $doctor->id, 'plan' => 'Hydration, rest, and targeted treatment after laboratory review.'],
            ['follow_up_date' => now()->addDays(3)->toDateString(), 'status' => ClinicalTreatmentPlan::STATUS_ACTIVE],
        );
        ClinicalTreatmentPlan::query()->firstOrCreate(
            ['encounter_id' => $encounters['closed']->id, 'author_id' => $doctor->id, 'plan' => 'Complete prescribed course and maintain hydration.'],
            ['follow_up_date' => now()->addDays(14)->toDateString(), 'status' => ClinicalTreatmentPlan::STATUS_COMPLETED, 'completed_at' => now()->subDay()->setTime(10, 10)],
        );

        ClinicalReferral::query()->firstOrCreate(
            ['encounter_id' => $encounters['active']->id, 'referrer_id' => $doctor->id, 'referred_to' => 'CityCare Laboratory'],
            ['reason' => 'Confirm or exclude malaria as part of fever assessment.', 'priority' => ClinicalReferral::PRIORITY_ROUTINE, 'status' => ClinicalReferral::STATUS_PENDING, 'notes' => self::MARKER.': laboratory referral context'],
        );
    }

    private function seedLaboratoryCatalog(Facility $facility): Collection
    {
        $definitions = [
            'MAL-RDT' => ['name' => 'Malaria rapid diagnostic test', 'description' => 'Rapid malaria antigen screen.', 'specimen_type' => 'Whole blood', 'result_type' => 'text', 'unit' => null, 'reference_range' => 'Negative'],
            'CBC' => ['name' => 'Complete blood count', 'description' => 'Full blood count panel.', 'specimen_type' => 'Whole blood', 'result_type' => 'text', 'unit' => 'x10^9/L', 'reference_range' => 'See laboratory reference interval'],
        ];

        return collect($definitions)->mapWithKeys(function (array $definition, string $code) use ($facility) {
            return [$code => LaboratoryTest::updateOrCreate(
                ['facility_id' => $facility->id, 'code' => $code],
                array_merge($definition, ['is_active' => true]),
            )];
        });
    }

    private function seedLaboratoryWork(Facility $facility, array $encounters, array $patients, Collection $tests, Collection $accounts): void
    {
        $doctor = $accounts['doctor@citycare.test'];
        $laboratory = $accounts['laboratory@citycare.test'];
        $openOrder = LaboratoryOrder::updateOrCreate(
            ['order_number' => 'DEMO-LAB-OPEN'],
            ['facility_id' => $facility->id, 'patient_id' => $patients['portal']->id, 'encounter_id' => $encounters['active']->id, 'ordered_by' => $doctor->id, 'status' => LaboratoryOrder::STATUS_ORDERED, 'notes' => self::MARKER.': waiting for malaria result', 'ordered_at' => now()->subMinutes(30), 'completed_at' => null, 'cancelled_at' => null],
        );
        LaboratoryOrderItem::updateOrCreate(
            ['laboratory_order_id' => $openOrder->id, 'laboratory_test_id' => $tests['MAL-RDT']->id],
            ['status' => LaboratoryOrderItem::STATUS_ORDERED, 'notes' => 'Priority routine', 'completed_at' => null, 'cancelled_at' => null],
        );

        $completedOrder = LaboratoryOrder::updateOrCreate(
            ['order_number' => 'DEMO-LAB-COMPLETED'],
            ['facility_id' => $facility->id, 'patient_id' => $patients['history']->id, 'encounter_id' => $encounters['closed']->id, 'ordered_by' => $doctor->id, 'status' => LaboratoryOrder::STATUS_COMPLETED, 'notes' => self::MARKER.': completed historical laboratory work', 'ordered_at' => now()->subDay()->setTime(9, 20), 'completed_at' => now()->subDay()->setTime(9, 45), 'cancelled_at' => null],
        );
        $completedItem = LaboratoryOrderItem::updateOrCreate(
            ['laboratory_order_id' => $completedOrder->id, 'laboratory_test_id' => $tests['CBC']->id],
            ['status' => LaboratoryOrderItem::STATUS_COMPLETED, 'notes' => null, 'completed_at' => now()->subDay()->setTime(9, 45), 'cancelled_at' => null],
        );
        LaboratoryResult::updateOrCreate(
            ['laboratory_order_item_id' => $completedItem->id],
            ['recorded_by' => $laboratory->id, 'result_value' => 'Within expected limits.', 'unit' => $tests['CBC']->unit, 'reference_range' => $tests['CBC']->reference_range, 'is_abnormal' => false, 'comments' => self::MARKER.': reviewed historical result', 'recorded_at' => now()->subDay()->setTime(9, 45)],
        );
    }

    private function seedMedicationCatalog(Facility $facility): array
    {
        $definitions = [
            'PCM500' => ['name' => 'Paracetamol 500mg', 'generic_name' => 'Paracetamol', 'route' => 'oral', 'dosage_form' => 'tablet', 'description' => 'Analgesic and antipyretic.', 'strength' => '500', 'unit' => 'mg', 'pack_size' => '100 tablets'],
            'AMOX500' => ['name' => 'Amoxicillin 500mg', 'generic_name' => 'Amoxicillin', 'route' => 'oral', 'dosage_form' => 'capsule', 'description' => 'Antibiotic capsule.', 'strength' => '500', 'unit' => 'mg', 'pack_size' => '100 capsules'],
        ];
        $medications = collect();
        $formulations = collect();

        foreach ($definitions as $code => $definition) {
            $medication = Medication::updateOrCreate(
                ['facility_id' => $facility->id, 'name' => $definition['name'], 'dosage_form' => $definition['dosage_form']],
                ['generic_name' => $definition['generic_name'], 'code' => $code, 'route' => $definition['route'], 'description' => $definition['description'], 'is_active' => true],
            );
            $medications->put($code, $medication);
            $formulations->put($code, MedicationFormulation::updateOrCreate(
                ['medication_id' => $medication->id, 'strength' => $definition['strength'], 'unit' => $definition['unit'], 'pack_size' => $definition['pack_size']],
                ['sku' => $code.'-'.$definition['dosage_form'], 'is_active' => true],
            ));
        }

        return [$medications, $formulations];
    }

    private function seedInventoryCatalog(Facility $facility, Collection $servicePoints): array
    {
        $pharmacyStore = InventoryStore::updateOrCreate(
            ['facility_id' => $facility->id, 'code' => 'PHARMACY-MAIN-STORE'],
            ['service_point_id' => $servicePoints['PHARMACY-MAIN']->id, 'name' => 'Main Pharmacy Store', 'type' => 'pharmacy', 'is_active' => true],
        );
        InventoryStore::updateOrCreate(
            ['facility_id' => $facility->id, 'code' => 'STORES-MAIN-STORE'],
            ['service_point_id' => $servicePoints['STORES-MAIN']->id, 'name' => 'Main Stores', 'type' => 'store', 'is_active' => true],
        );

        $definitions = [
            'PCM500' => ['name' => 'Paracetamol 500mg', 'sku' => 'PCM500-TAB', 'category' => 'Medication', 'unit' => 'tablet', 'reorder_level' => 20],
            'AMOX500' => ['name' => 'Amoxicillin 500mg', 'sku' => 'AMOX500-CAP', 'category' => 'Medication', 'unit' => 'capsule', 'reorder_level' => 10],
            'GLOVES-M' => ['name' => 'Examination gloves medium', 'sku' => 'GLOVES-M-100', 'category' => 'Consumable', 'unit' => 'box', 'reorder_level' => 5],
        ];
        $items = collect($definitions)->mapWithKeys(function (array $definition, string $code) use ($facility) {
            return [$code => InventoryItem::updateOrCreate(
                ['facility_id' => $facility->id, 'code' => $code],
                array_merge($definition, ['is_active' => true]),
            )];
        });
        InventorySupplier::updateOrCreate(
            ['facility_id' => $facility->id, 'code' => 'CITYCARE-MED-SUPPLY'],
            ['name' => 'CityCare Medical Supplies', 'phone' => '+256700010000', 'email' => 'supplies@citycare.test', 'address' => 'Kampala, Uganda', 'is_active' => true],
        );

        return [$pharmacyStore, $items];
    }

    private function seedProcurement(Facility $facility, InventoryStore $pharmacyStore, Collection $inventoryItems, User $inventoryOfficer): void
    {
        $marker = self::MARKER.': initial pharmacy stock receipt';
        $order = $pharmacyStore->purchaseOrders()->where('notes', $marker)->first();
        $service = app(InventoryProcurementService::class);

        if (! $order) {
            $supplier = InventorySupplier::query()->where('facility_id', $facility->id)->where('code', 'CITYCARE-MED-SUPPLY')->firstOrFail();
            $order = $service->createPurchaseOrder($inventoryOfficer, $supplier, $pharmacyStore, [
                'ordered_at' => now()->subDays(2)->toDateString(),
                'notes' => $marker,
                'items' => [
                    ['inventory_item_id' => $inventoryItems['PCM500']->id, 'quantity_ordered' => 80, 'unit_cost' => 120],
                    ['inventory_item_id' => $inventoryItems['AMOX500']->id, 'quantity_ordered' => 8, 'unit_cost' => 350],
                    ['inventory_item_id' => $inventoryItems['GLOVES-M']->id, 'quantity_ordered' => 3, 'unit_cost' => 18000],
                ],
            ]);
            $order->update(['status' => 'ordered']);
        }

        if (! $order->fresh()->goodsReceipts()->exists()) {
            $items = $order->fresh('items')->items;
            $service->receiveStock($inventoryOfficer, $order->fresh(), $pharmacyStore, [
                'received_at' => now()->subDay()->setTime(8, 0),
                'notes' => $marker,
                'items' => $items->map(fn ($item) => [
                    'purchase_order_item_id' => $item->id,
                    'quantity_received' => $item->quantity_ordered,
                    'unit_cost' => $item->unit_cost,
                ])->all(),
            ]);
        }
    }

    private function seedPrescriptions(array $encounters, Collection $medications, Collection $formulations, InventoryStore $pharmacyStore, Collection $accounts): void
    {
        $doctor = $accounts['doctor@citycare.test'];
        $pharmacyOfficer = $accounts['pharmacy@citycare.test'];
        $pendingMarker = self::MARKER.': prescription awaiting pharmacy';
        if (! Prescription::query()->where('notes', $pendingMarker)->exists()) {
            app(PharmacyService::class)->prescribe($encounters['active'], $doctor, [
                'notes' => $pendingMarker,
                'items' => [[
                    'medication_id' => $medications['AMOX500']->id,
                    'medication_formulation_id' => $formulations['AMOX500']->id,
                    'quantity' => 10,
                    'dose' => '500 mg',
                    'frequency' => 'Three times daily',
                    'duration' => '5 days',
                    'instructions' => 'Take after meals. Stock is intentionally below the requested amount for low-stock workflow review.',
                ]],
            ]);
        }

        $completedMarker = self::MARKER.': historical dispensed prescription';
        $completed = Prescription::query()->where('notes', $completedMarker)->first();
        if (! $completed) {
            $completed = Prescription::create([
                'facility_id' => $encounters['closed']->facility_id,
                'patient_id' => $encounters['closed']->patient_id,
                'encounter_id' => $encounters['closed']->id,
                'prescribed_by' => $doctor->id,
                'prescription_number' => 'DEMO-RX-DISPENSED',
                'status' => Prescription::STATUS_PRESCRIBED,
                'notes' => $completedMarker,
                'prescribed_at' => now()->subDay()->setTime(9, 40),
            ]);
            $completed->items()->create([
                'medication_id' => $medications['PCM500']->id,
                'medication_formulation_id' => $formulations['PCM500']->id,
                'quantity' => 20,
                'dose' => '1 tablet',
                'route' => 'oral',
                'frequency' => 'Every 8 hours when needed',
                'duration' => '3 days',
                'instructions' => 'Take with water after meals if needed for fever or pain.',
                'status' => 'prescribed',
            ]);
        }

        if (! $completed->dispensings()->exists()) {
            $item = $completed->items()->firstOrFail();
            app(PharmacyInventoryDispensingService::class)->dispenseWithInventory(
                $completed,
                $pharmacyOfficer,
                $pharmacyStore,
                [['prescription_item_id' => $item->id, 'quantity_dispensed' => 20]],
                self::MARKER.': historical stock issue',
            );
        }
    }

    private function seedBilling(Facility $facility, array $patients, array $encounters, Collection $accounts): void
    {
        $cashier = $accounts['cashier@citycare.test'];
        $consultation = BillableService::updateOrCreate(
            ['facility_id' => $facility->id, 'code' => 'CONSULT-OPD'],
            ['name' => 'Outpatient consultation', 'category' => 'Consultation', 'description' => 'General outpatient clinician consultation.', 'unit' => 'visit', 'is_active' => true],
        );
        $laboratory = BillableService::updateOrCreate(
            ['facility_id' => $facility->id, 'code' => 'LAB-CBC'],
            ['name' => 'Complete blood count', 'category' => 'Laboratory', 'description' => 'Complete blood count test.', 'unit' => 'test', 'is_active' => true],
        );
        $consultationPrice = $this->syncServicePrice($facility, $consultation, 50000);
        $laboratoryPrice = $this->syncServicePrice($facility, $laboratory, 30000);

        $billing = app(BillingService::class);
        $activeCharge = $billing->addCharge($cashier, $patients['portal'], $consultation, $consultationPrice, [
            'encounter' => $encounters['active'],
            'description' => 'Outpatient consultation - active encounter',
            'idempotency_key' => 'citycare-demo-active-consultation',
        ]);
        $activeInvoiceMarker = self::MARKER.': active outstanding invoice';
        if (! Invoice::query()->where('notes', $activeInvoiceMarker)->exists()) {
            $billing->createInvoice($cashier, $patients['portal'], [$activeCharge], [
                'encounter_id' => $encounters['active']->id,
                'notes' => $activeInvoiceMarker,
            ]);
        }

        $historyCharge = $billing->addCharge($cashier, $patients['history'], $laboratory, $laboratoryPrice, [
            'description' => 'Complete blood count - historical visit',
            'idempotency_key' => 'citycare-demo-historical-cbc',
        ]);
        $historyInvoiceMarker = self::MARKER.': historical partial invoice';
        $historyInvoice = Invoice::query()->where('notes', $historyInvoiceMarker)->first();
        if (! $historyInvoice) {
            $historyInvoice = $billing->createInvoice($cashier, $patients['history'], [$historyCharge], ['notes' => $historyInvoiceMarker]);
        }
        if (! Payment::query()->where('transaction_reference', 'CITYCARE-DEMO-PAYMENT-001')->exists()) {
            $billing->recordPayment($cashier, $historyInvoice, 10000, Payment::METHOD_MOBILE_MONEY, [
                'transaction_reference' => 'CITYCARE-DEMO-PAYMENT-001',
                'notes' => self::MARKER.': partial payment',
            ]);
        }
    }

    private function syncServicePrice(Facility $facility, BillableService $service, float $amount): ServicePrice
    {
        $price = ServicePrice::query()
            ->where('billable_service_id', $service->id)
            ->whereDate('effective_from', '2025-01-01')
            ->first();

        if ($price) {
            $price->update([
                'facility_id' => $facility->id,
                'amount' => $amount,
                'currency' => 'UGX',
                'effective_to' => null,
                'is_active' => true,
                'notes' => self::MARKER,
            ]);

            return $price->refresh();
        }

        return ServicePrice::create([
            'facility_id' => $facility->id,
            'billable_service_id' => $service->id,
            'amount' => $amount,
            'currency' => 'UGX',
            'effective_from' => '2025-01-01',
            'effective_to' => null,
            'is_active' => true,
            'notes' => self::MARKER,
        ]);
    }

    private function seedReportDefinitions(): void
    {
        $definitions = [
            'clinical_activity' => ['name' => 'Clinical activity', 'category' => 'Clinical', 'description' => 'Encounter volumes and statuses.', 'supported_filters' => ['facility_id', 'date_from', 'date_to']],
            'laboratory_activity' => ['name' => 'Laboratory activity', 'category' => 'Laboratory', 'description' => 'Laboratory order volumes and statuses.', 'supported_filters' => ['facility_id', 'date_from', 'date_to']],
            'pharmacy_activity' => ['name' => 'Pharmacy activity', 'category' => 'Pharmacy', 'description' => 'Prescription statuses and dispensing activity.', 'supported_filters' => ['facility_id', 'date_from', 'date_to']],
            'billing_summary' => ['name' => 'Billing summary', 'category' => 'Finance', 'description' => 'Invoice totals, collections, and outstanding balances.', 'supported_filters' => ['facility_id', 'date_from', 'date_to']],
            'inventory_summary' => ['name' => 'Inventory summary', 'category' => 'Inventory', 'description' => 'Stock balance and availability summary.', 'supported_filters' => ['facility_id']],
        ];

        foreach ($definitions as $code => $definition) {
            ReportDefinition::updateOrCreate(['code' => $code], array_merge($definition, ['is_active' => true]));
        }
    }

    private function seedAuditEvents(Facility $facility, array $patients, array $encounters, Collection $accounts): void
    {
        $events = [
            ['event_type' => 'patient.registered', 'action' => 'created', 'auditable_type' => Patient::class, 'auditable_id' => $patients['portal']->id, 'actor' => 'reception@citycare.test', 'after_values' => ['medical_record_number' => $patients['portal']->medical_record_number]],
            ['event_type' => 'encounter.opened', 'action' => 'created', 'auditable_type' => ClinicalEncounter::class, 'auditable_id' => $encounters['active']->id, 'actor' => 'doctor@citycare.test', 'after_values' => ['encounter_number' => $encounters['active']->encounter_number]],
        ];

        foreach ($events as $event) {
            AuditEvent::query()->firstOrCreate(
                ['event_type' => $event['event_type'], 'action' => $event['action'], 'auditable_type' => $event['auditable_type'], 'auditable_id' => $event['auditable_id']],
                ['actor_id' => $accounts[$event['actor']]->id, 'facility_id' => $facility->id, 'before_values' => null, 'after_values' => $event['after_values'], 'context' => ['source' => self::MARKER], 'ip_address' => null, 'user_agent' => null, 'occurred_at' => now()],
            );
        }
    }
}
