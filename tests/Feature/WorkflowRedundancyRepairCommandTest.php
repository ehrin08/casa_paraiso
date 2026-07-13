<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowRedundancyRepairCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_defaults_to_dry_run_and_apply_is_idempotent(): void
    {
        $admin = User::factory()->admin()->create();
        $service = Service::factory()->create(['price' => 850]);
        $appointment = Appointment::factory()->for($service)->create([
            'quoted_amount' => 850,
            'status' => Appointment::STATUS_PENDING,
            'staff_profile_id' => null,
            'scheduled_start_at' => null,
            'scheduled_end_at' => null,
            'confirmed_at' => null,
        ]);

        $this->artisan('casa:repair-workflow-redundancy')
            ->expectsOutputToContain($appointment->appointment_number)
            ->expectsOutputToContain('Dry-run complete. No data was changed.')
            ->assertSuccessful();

        $this->assertSame(Appointment::STATUS_PENDING, $appointment->fresh()->status);

        $this->artisan('casa:repair-workflow-redundancy', [
            '--apply' => true,
            '--actor' => $admin->id,
        ])->assertSuccessful();

        $appointment->refresh();
        $this->assertSame(Appointment::STATUS_CANCELLED, $appointment->status);
        $this->assertSame($admin->id, $appointment->cancelled_by);
        $this->assertDatabaseHas('appointment_status_logs', [
            'appointment_id' => $appointment->id,
            'from_status' => Appointment::STATUS_PENDING,
            'to_status' => Appointment::STATUS_CANCELLED,
            'changed_by' => $admin->id,
            'reason' => 'Closed during immediate-confirmation remediation',
        ]);

        $this->artisan('casa:repair-workflow-redundancy', [
            '--apply' => true,
            '--actor' => $admin->id,
        ])->assertSuccessful();

        $this->assertDatabaseCount('appointment_status_logs', 1);
    }
}
