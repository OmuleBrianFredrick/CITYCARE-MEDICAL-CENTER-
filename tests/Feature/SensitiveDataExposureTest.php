<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ClinicalReferralAttachment;
use App\Models\EmployeeInvitation;
use App\Models\User;
use Tests\TestCase;

class SensitiveDataExposureTest extends TestCase
{
    public function test_user_credentials_are_not_serialized(): void
    {
        $user = new User([
            'name' => 'Security Review',
            'email' => 'security@example.com',
            'password' => 'secret-password',
        ]);

        $user->remember_token = 'remember-secret';

        $serialized = $user->toArray();

        $this->assertArrayNotHasKey('password', $serialized);
        $this->assertArrayNotHasKey('remember_token', $serialized);
    }

    public function test_employee_invitation_token_hash_is_not_serialized(): void
    {
        $invitation = new EmployeeInvitation([
            'email' => 'staff@example.com',
            'name' => 'Staff Member',
            'role_slug' => 'doctor',
            'token_hash' => 'sensitive-token-hash',
            'status' => EmployeeInvitation::STATUS_PENDING,
        ]);

        $this->assertArrayNotHasKey('token_hash', $invitation->toArray());
    }

    public function test_private_attachment_storage_metadata_is_not_serialized(): void
    {
        $attachment = new ClinicalReferralAttachment([
            'disk' => 'private',
            'file_path' => 'clinical-referrals/secret-document.pdf',
            'file_name' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ]);

        $serialized = $attachment->toArray();

        $this->assertArrayNotHasKey('disk', $serialized);
        $this->assertArrayNotHasKey('file_path', $serialized);
        $this->assertSame('document.pdf', $serialized['file_name']);
    }

    public function test_audit_request_metadata_is_not_serialized(): void
    {
        $auditEvent = new AuditEvent([
            'event_type' => 'security.review',
            'action' => 'serialized',
            'ip_address' => '203.0.113.10',
            'user_agent' => 'Sensitive Data Exposure Test Agent',
        ]);

        $serialized = $auditEvent->toArray();

        $this->assertArrayNotHasKey('ip_address', $serialized);
        $this->assertArrayNotHasKey('user_agent', $serialized);
        $this->assertSame('security.review', $serialized['event_type']);
    }
}
