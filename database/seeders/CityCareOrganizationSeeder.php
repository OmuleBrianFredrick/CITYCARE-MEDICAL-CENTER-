<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Facility;
use App\Models\ServicePoint;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class CityCareOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        $facility = Facility::updateOrCreate(
            ['name' => 'CityCare Medical Center'],
            [
                'legal_name' => 'CityCare Medical Center',
                'country' => 'Uganda',
                'timezone' => 'Africa/Kampala',
                'currency' => 'UGX',
                'primary_color' => '#2563EB',
                'secondary_color' => '#0F172A',
                'accent_color' => '#F4C430',
                'is_active' => true,
            ]
        );

        $departments = [
            ['name' => 'Reception & Front Desk', 'code' => 'RECEPTION', 'sort_order' => 10],
            ['name' => 'Outpatient Department', 'code' => 'OPD', 'sort_order' => 20],
            ['name' => 'Nursing & Triage', 'code' => 'NURSING', 'sort_order' => 30],
            ['name' => 'Laboratory', 'code' => 'LAB', 'sort_order' => 40],
            ['name' => 'Pharmacy', 'code' => 'PHARMACY', 'sort_order' => 50],
            ['name' => 'Finance & Billing', 'code' => 'FINANCE', 'sort_order' => 60],
            ['name' => 'Medical Records', 'code' => 'RECORDS', 'sort_order' => 70],
            ['name' => 'Inventory & Stores', 'code' => 'STORES', 'sort_order' => 80],
        ];

        foreach ($departments as $definition) {
            $department = Department::updateOrCreate(
                ['facility_id' => $facility->id, 'code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                ]
            );

            $servicePoint = match ($definition['code']) {
                'RECEPTION' => ['name' => 'Main Reception', 'code' => 'RECEPTION-MAIN', 'type' => 'reception'],
                'OPD' => ['name' => 'General Consultation', 'code' => 'OPD-GENERAL', 'type' => 'clinic'],
                'NURSING' => ['name' => 'Triage', 'code' => 'NURSING-TRIAGE', 'type' => 'triage'],
                'LAB' => ['name' => 'Sample Collection', 'code' => 'LAB-SAMPLE', 'type' => 'laboratory'],
                'PHARMACY' => ['name' => 'Main Pharmacy', 'code' => 'PHARMACY-MAIN', 'type' => 'pharmacy'],
                'FINANCE' => ['name' => 'Main Cashier', 'code' => 'FINANCE-CASHIER', 'type' => 'cashier'],
                'RECORDS' => ['name' => 'Medical Records Desk', 'code' => 'RECORDS-MAIN', 'type' => 'records'],
                'STORES' => ['name' => 'Main Stores', 'code' => 'STORES-MAIN', 'type' => 'stores'],
            };

            ServicePoint::updateOrCreate(
                ['code' => $servicePoint['code']],
                array_merge($servicePoint, [
                    'department_id' => $department->id,
                    'is_active' => true,
                ])
            );
        }

        $settings = [
            ['key' => 'appointments.default_duration', 'value' => '30', 'type' => 'integer', 'group' => 'appointments'],
            ['key' => 'appointments.reminders.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'notifications'],
            ['key' => 'patient.registration.require_phone', 'value' => '0', 'type' => 'boolean', 'group' => 'patients'],
            ['key' => 'notifications.email.enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'notifications'],
            ['key' => 'notifications.sms.enabled', 'value' => '0', 'type' => 'boolean', 'group' => 'notifications'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
