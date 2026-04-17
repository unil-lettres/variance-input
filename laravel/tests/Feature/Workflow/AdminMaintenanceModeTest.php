<?php

namespace Tests\Feature\Workflow;

use App\Services\AdminMaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminMaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(AdminMaintenanceMode::class)->deactivate();
        app(AdminMaintenanceMode::class)->clearAnnouncement();

        parent::tearDown();
    }

    public function test_maintenance_splash_blocks_non_admin_web_requests(): void
    {
        $this->signInEditor();
        app(AdminMaintenanceMode::class)->activate('Déploiement en cours.');

        $this->get('/')
            ->assertStatus(503)
            ->assertSee('Interface d’édition momentanément indisponible')
            ->assertSee('Déploiement en cours.');
    }

    public function test_login_form_remains_accessible_during_maintenance(): void
    {
        app(AdminMaintenanceMode::class)->activate('Déploiement en cours.');

        $this->get('/login')
            ->assertOk()
            ->assertSee('Connexion');
    }

    public function test_maintenance_mode_returns_json_for_admin_api_requests(): void
    {
        app(AdminMaintenanceMode::class)->activate('Déploiement en cours.');

        $this->getJson('/api/authors')
            ->assertStatus(503)
            ->assertJsonPath('status', 'maintenance')
            ->assertJsonPath('message', 'Déploiement en cours.');
    }

    public function test_authenticated_admin_can_bypass_maintenance_mode(): void
    {
        $this->signInAdmin();
        app(AdminMaintenanceMode::class)->activate('Déploiement en cours.');

        $this->get('/account/password')
            ->assertOk()
            ->assertSee('Mot de passe');
    }

    public function test_authenticated_editor_is_blocked_by_maintenance_mode(): void
    {
        $this->signInEditor();
        app(AdminMaintenanceMode::class)->activate('Déploiement en cours.');

        $this->get('/account/password')
            ->assertStatus(503)
            ->assertSee('Déploiement en cours.');
    }

    public function test_public_health_endpoint_returns_minimal_status_only(): void
    {
        $response = $this->getJson('/health');

        $this->assertContains($response->status(), [200, 503]);
        $this->assertContains($response->json('status'), ['ok', 'not_ok']);
        $this->assertSame(['status'], array_keys($response->json()));
    }

    public function test_admin_health_report_keeps_detailed_maintenance_state_private(): void
    {
        $this->signInAdmin();
        app(AdminMaintenanceMode::class)->activate(
            'Déploiement en cours.',
            now()->addMinutes(15),
            true,
        );

        $response = $this->get('/health/report');

        $this->assertContains($response->status(), [200, 503]);

        $response
            ->assertSee('État du système')
            ->assertSee('Déploiement en cours.');
    }

    public function test_planned_maintenance_announcement_is_shown_on_welcome_page(): void
    {
        $this->signInEditor();

        app(AdminMaintenanceMode::class)->announce(
            'Déploiement prévu demain matin.',
            now()->addDay()->setTime(9, 30),
            now()->addDay()->setTime(10, 0),
        );

        $this->get('/')
            ->assertOk()
            ->assertSee('Maintenance annoncée')
            ->assertSee('Déploiement prévu demain matin.')
            ->assertSee('Début prévu :')
            ->assertSee('Fin estimée :');
    }

    public function test_artisan_commands_toggle_admin_maintenance_mode(): void
    {
        $this->artisan('admin:maintenance:on', [
            '--message' => 'Pause de maintenance.',
            '--until' => now()->addHour()->format('Y-m-d H:i'),
            '--no-admin-bypass' => true,
        ])->assertSuccessful();

        $state = app(AdminMaintenanceMode::class)->currentState();
        $this->assertTrue($state['enabled']);
        $this->assertSame('Pause de maintenance.', $state['message']);
        $this->assertFalse($state['allow_admins']);
        $this->assertNotNull($state['until']);

        $this->artisan('admin:maintenance:off')
            ->assertSuccessful();

        $this->assertFalse(app(AdminMaintenanceMode::class)->isEnabled());
    }

    public function test_artisan_commands_toggle_admin_maintenance_announcement(): void
    {
        $this->artisan('admin:maintenance:announce', [
            '--message' => 'Intervention prévue.',
            '--starts' => now()->addHour()->format('Y-m-d H:i'),
            '--until' => now()->addHours(2)->format('Y-m-d H:i'),
        ])->assertSuccessful();

        $state = app(AdminMaintenanceMode::class)->currentAnnouncement();
        $this->assertTrue($state['enabled']);
        $this->assertSame('Intervention prévue.', $state['message']);
        $this->assertNotNull($state['starts_at']);
        $this->assertNotNull($state['until']);

        $this->artisan('admin:maintenance:announce:clear')
            ->assertSuccessful();

        $this->assertFalse(app(AdminMaintenanceMode::class)->currentAnnouncement()['enabled']);
    }

    public function test_maintenance_state_survives_cache_flush(): void
    {
        app(AdminMaintenanceMode::class)->activate('Déploiement en cours.');

        Cache::flush();

        $state = app(AdminMaintenanceMode::class)->currentState();
        $this->assertTrue($state['enabled']);
        $this->assertSame('Déploiement en cours.', $state['message']);
    }

    public function test_health_report_marks_pending_migrations(): void
    {
        $this->signInAdmin();

        $path = database_path('migrations/2099_12_31_235959_health_pending_migration.php');
        File::put($path, "<?php\n");

        try {
            $response = $this->get('/health/report');

            $this->assertContains($response->status(), [200, 503]);
            $response
                ->assertSee('Migrations')
                ->assertSeeText('en attente');
        } finally {
            @unlink($path);
        }
    }

    public function test_health_report_lists_critical_legacy_path_checks(): void
    {
        $this->signInAdmin();

        $response = $this->get('/health/report');

        $this->assertContains($response->status(), [200, 503]);
        $response
            ->assertSeeText('uploads_legacy')
            ->assertSeeText('uploads_images_legacy')
            ->assertSeeText('uploads_pdf_legacy')
            ->assertSeeText('Accès attendu');
    }
}
