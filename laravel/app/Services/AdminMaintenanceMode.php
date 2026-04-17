<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;

class AdminMaintenanceMode
{
    private const CACHE_KEY = 'admin_maintenance.state';
    private const ANNOUNCEMENT_CACHE_KEY = 'admin_maintenance.announcement';

    public function currentState(): array
    {
        $state = $this->readPersistedState(
            $this->statePath(),
            self::CACHE_KEY,
        );

        if (! is_array($state) || ! ($state['enabled'] ?? false)) {
            return $this->disabledState();
        }

        $until = $this->normalizeDate($state['until'] ?? null);

        if ($until && $until->isPast()) {
            $this->deactivate();

            return $this->disabledState();
        }

        return [
            'enabled' => true,
            'message' => trim((string) ($state['message'] ?? '')) ?: $this->defaultMessage(),
            'until' => $until?->toIso8601String(),
            'enabled_at' => $this->normalizeDate($state['enabled_at'] ?? null)?->toIso8601String(),
            'allow_admins' => (bool) ($state['allow_admins'] ?? true),
        ];
    }

    public function activate(?string $message = null, ?Carbon $until = null, bool $allowAdmins = true): array
    {
        $state = [
            'enabled' => true,
            'message' => trim((string) $message) ?: $this->defaultMessage(),
            'until' => $until?->toIso8601String(),
            'enabled_at' => now()->toIso8601String(),
            'allow_admins' => $allowAdmins,
        ];

        $this->writePersistedState($this->statePath(), $state);

        return $state;
    }

    public function currentAnnouncement(): array
    {
        $announcement = $this->readPersistedState(
            $this->announcementPath(),
            self::ANNOUNCEMENT_CACHE_KEY,
        );

        if (! is_array($announcement) || ! ($announcement['enabled'] ?? false)) {
            return $this->disabledAnnouncement();
        }

        $startsAt = $this->normalizeDate($announcement['starts_at'] ?? null);
        $until = $this->normalizeDate($announcement['until'] ?? null);

        if ($until && $until->isPast()) {
            $this->clearAnnouncement();

            return $this->disabledAnnouncement();
        }

        return [
            'enabled' => true,
            'message' => trim((string) ($announcement['message'] ?? '')) ?: $this->defaultAnnouncementMessage(),
            'starts_at' => $startsAt?->toIso8601String(),
            'until' => $until?->toIso8601String(),
            'created_at' => $this->normalizeDate($announcement['created_at'] ?? null)?->toIso8601String(),
        ];
    }

    public function announce(?string $message = null, ?Carbon $startsAt = null, ?Carbon $until = null): array
    {
        $state = [
            'enabled' => true,
            'message' => trim((string) $message) ?: $this->defaultAnnouncementMessage(),
            'starts_at' => $startsAt?->toIso8601String(),
            'until' => $until?->toIso8601String(),
            'created_at' => now()->toIso8601String(),
        ];

        $this->writePersistedState($this->announcementPath(), $state);

        return $state;
    }

    public function deactivate(): void
    {
        @unlink($this->statePath());
        Cache::forget(self::CACHE_KEY);
    }

    public function clearAnnouncement(): void
    {
        @unlink($this->announcementPath());
        Cache::forget(self::ANNOUNCEMENT_CACHE_KEY);
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->currentState()['enabled'] ?? false);
    }

    public function allowsAdminBypass(): bool
    {
        return (bool) ($this->currentState()['allow_admins'] ?? true);
    }

    public function shouldBypassFor(?User $user): bool
    {
        return $this->isEnabled()
            && $this->allowsAdminBypass()
            && $user instanceof User
            && (bool) $user->is_admin;
    }

    public function publicPayload(): array
    {
        $state = $this->currentState();

        return [
            'status' => 'maintenance',
            'message' => $state['message'],
            'until' => $state['until'],
            'allow_admins' => $state['allow_admins'],
        ];
    }

    public function disabledState(): array
    {
        return [
            'enabled' => false,
            'message' => $this->defaultMessage(),
            'until' => null,
            'enabled_at' => null,
            'allow_admins' => true,
        ];
    }

    public function disabledAnnouncement(): array
    {
        return [
            'enabled' => false,
            'message' => $this->defaultAnnouncementMessage(),
            'starts_at' => null,
            'until' => null,
            'created_at' => null,
        ];
    }

    private function normalizeDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function defaultMessage(): string
    {
        return 'Maintenance en cours. L’atelier éditorial Variance sera de retour dans quelques minutes.';
    }

    private function defaultAnnouncementMessage(): string
    {
        return 'Une opération de maintenance de l’atelier éditorial Variance est prévue prochainement.';
    }

    private function statePath(): string
    {
        return storage_path('app/private/admin_maintenance_state.json');
    }

    private function announcementPath(): string
    {
        return storage_path('app/private/admin_maintenance_announcement.json');
    }

    private function readPersistedState(string $path, string $legacyCacheKey): ?array
    {
        $fromFile = $this->readJsonFile($path);
        if (is_array($fromFile)) {
            return $fromFile;
        }

        $legacyState = Cache::get($legacyCacheKey);
        if (! is_array($legacyState)) {
            return null;
        }

        $this->writePersistedState($path, $legacyState);
        Cache::forget($legacyCacheKey);

        return $legacyState;
    }

    private function writePersistedState(string $path, array $state): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put(
            $path,
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function readJsonFile(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
