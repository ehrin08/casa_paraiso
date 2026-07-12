<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

class SeederSafetyTest extends TestCase
{
    public function test_demo_seeder_refuses_to_run_in_production(): void
    {
        $application = app();
        $originalEnvironment = $application->environment();
        $application->detectEnvironment(fn () => 'production');

        try {
            (new DatabaseSeeder)->run();
            $this->fail('The demo seeder ran in production.');
        } catch (\LogicException $exception) {
            $this->assertSame(
                'Demo data must not be seeded in production. Run migrations without --seed.',
                $exception->getMessage(),
            );
        } finally {
            $application->detectEnvironment(fn () => $originalEnvironment);
        }
    }
}
