<?php

namespace Tests\Feature\Workflow;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityRouteAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_non_public_laravel_routes_require_authentication(): void
    {
        $publicRoutes = [
            'GET health',
            'GET login',
            'POST login',
            'GET maintenance',
        ];

        $violations = [];

        foreach (Route::getRoutes() as $route) {
            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            $uri = $route->uri();
            $middleware = $route->gatherMiddleware();

            foreach ($methods as $method) {
                if (in_array("{$method} {$uri}", $publicRoutes, true)) {
                    continue;
                }

                if (! in_array('auth', $middleware, true)) {
                    $violations[] = "{$method} {$uri}";
                }
            }
        }

        $this->assertSame([], $violations);
    }

    public function test_guest_can_only_reach_login_and_minimal_health_endpoints(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Connexion');

        $this->post('/login', [
            'email' => 'missing@example.test',
            'password' => 'invalid',
        ])->assertRedirect();

        $health = $this->getJson('/health');
        $this->assertContains($health->status(), [200, 503]);
        $this->assertContains($health->json('status'), ['ok', 'not_ok']);
        $this->assertSame(['status'], array_keys($health->json()));

        $this->get('/')->assertRedirect('/admin/login');
        $this->get('/register')->assertRedirect('/admin/login');
        $this->get('/users')->assertRedirect('/admin/login');
        $this->get('/health/report')->assertRedirect('/admin/login');
        $this->get('/comparisons/by-work')->assertRedirect('/admin/login');

        $this->getJson('/api/authors')->assertUnauthorized();
        $this->getJson('/api/facsimiles/space')->assertUnauthorized();

        $this->get('/up')->assertNotFound();
        $this->get('/storage/private/queue_workers.json')->assertNotFound();
    }

    public function test_publish_api_routes_use_session_auth_stack(): void
    {
        $publish = Route::getRoutes()->match(request()->create('/api/publish_xhtml', 'POST'));
        $unpublish = Route::getRoutes()->match(request()->create('/api/publish_xhtml/123', 'DELETE'));

        foreach ([$publish, $unpublish] as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertContains('web', $middleware);
            $this->assertContains('auth', $middleware);
        }
    }
}
