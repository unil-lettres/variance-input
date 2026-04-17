<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class AdminMaintenanceMode
{
    private const CACHE_KEY = 'admin_maintenance.state';
    private const ANNOUNCEMENT_CACHE_KEY = 'admin_maintenance.announcement';

    public function currentState(): array
    {
        $state = Cache::get(self::CACHE_KEY);

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

        Cache::forever(self::CACHE_KEY, $state);

        return $state;
    }

    public function currentAnnouncement(): array
    {
        $announcement = Cache::get(self::ANNOUNCEMENT_CACHE_KEY);

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

        Cache::forever(self::ANNOUNCEMENT_CACHE_KEY, $state);

        return $state;
    }

    public function deactivate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function clearAnnouncement(): void
    {
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
}
