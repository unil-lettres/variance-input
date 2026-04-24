<?php

namespace Tests\Feature\Workflow;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RoutesAndSchedulerConfigurationTest extends TestCase
{
    public function test_documented_routes_are_registered_with_expected_methods(): void
    {
        $this->assertRouteMatches('POST', '/api/versions/1/pagination/merge-from-pb', 'api/versions/{version}/pagination/merge-from-pb');
        $this->assertRouteMatches('POST', '/api/versions/1/reader/rebuild', 'api/versions/{version}/reader/rebuild');
        $this->assertRouteMatches('PATCH', '/comparisons/1/comments', 'comparisons/{comparison}/comments');
        $this->assertRouteMatches('GET', '/comparisons/1/export/status', 'comparisons/{comparison}/export/status');
        $this->assertRouteMatches('POST', '/chapters/import/preview', 'chapters/import/preview');
        $this->assertRouteMatches('POST', '/chapters/import/commit', 'chapters/import/commit');
        $this->assertRouteMatches('POST', '/api/publish_xhtml', 'api/publish_xhtml');
        $this->assertRouteMatches('POST', '/api/upload_facsimiles', 'api/upload_facsimiles');
    }

    public function test_named_routes_used_in_docs_keep_expected_paths(): void
    {
        $this->assertSame('/comparisons/123/export/status', route('comparisons.export.status', ['comparison' => 123], false));
        $this->assertSame('/chapters/import/preview', route('chapters.import.preview', [], false));
        $this->assertSame('/maintenance', route('maintenance.notice', [], false));
    }

    public function test_scheduler_registers_health_heartbeat_and_daily_database_backup(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->map(fn ($event) => [
                'command' => (string) $event->command,
                'expression' => $event->getExpression(),
            ]);

        $this->assertTrue($events->contains(fn (array $event) => str_contains($event['command'], 'health:scheduler-heartbeat') && $event['expression'] === '* * * * *'));
        $this->assertTrue($events->contains(fn (array $event) => str_contains($event['command'], 'backup:database --retention-days=14') && $event['expression'] === '15 3 * * *'));
    }

    private function assertRouteMatches(string $method, string $path, string $expectedUri): void
    {
        $route = Route::getRoutes()->match(Request::create($path, $method));

        $this->assertSame($expectedUri, $route->uri(), sprintf('Unexpected route match for %s %s', $method, $path));
        $this->assertContains($method, $route->methods(), sprintf('Method %s is not registered for %s', $method, $path));
    }
}
