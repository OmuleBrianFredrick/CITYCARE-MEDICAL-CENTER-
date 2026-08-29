<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\PatientNotificationService;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'citycare:send-appointment-reminders {--hours=24 : Reminder window in hours}';

    protected $description = 'Create idempotent patient-portal reminders for upcoming appointments';

    public function handle(PatientNotificationService $notifications): int
    {
        $hours = filter_var($this->option('hours'), FILTER_VALIDATE_INT);
        if ($hours === false || $hours < 1 || $hours > 168) {
            $this->error('The --hours option must be a whole number between 1 and 168.');

            return self::INVALID;
        }

        if (! $notifications->appointmentRemindersEnabled()) {
            $this->info('Appointment reminders are disabled in organization settings.');

            return self::SUCCESS;
        }

        $from = now();
        $until = $from->copy()->addHours($hours);
        $processed = 0;

        Appointment::query()
            ->where('status', Appointment::STATUS_SCHEDULED)
            ->whereBetween('scheduled_start', [$from, $until])
            ->whereHas('patient.user', fn ($query) => $query->where('user_type', 'patient'))
            ->with(['patient.user', 'department', 'servicePoint', 'provider'])
            ->orderBy('id')
            ->chunkById(100, function ($appointments) use ($notifications, $hours, &$processed): void {
                foreach ($appointments as $appointment) {
                    if ($notifications->appointmentReminder($appointment, $hours)) {
                        $processed++;
                    }
                }
            });

        $this->info("Processed {$processed} eligible appointment reminder(s).");

        return self::SUCCESS;
    }
}
