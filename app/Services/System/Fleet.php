<?php

namespace App\Services\System;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * The app fleet as the System page shows it: which builds are in the field,
 * on which platform, and how many users push can reach.
 *
 * - versions — Devices of app users grouped by app_version × build_profile,
 *   newest version first (semantic compare), "unknown" (a null version)
 *   last. Each row has iOS and Android counts, `latest` on the newest
 *   production version and `old` on production versions that are Old Builds.
 * - platform — iOS and Android Devices of app users.
 * - push — app users with at least one Device, split by their Push Switch.
 * - old_build_users — app users whose most recently seen Device is an Old
 *   Build; the same users the Users list `old_build=1` filter shows.
 *
 * The Old Build rule itself lives in OldBuilds. Staff Devices still count
 * towards which versions are newest ("any Device reports"), but never in a
 * count here: every count is of app users. Live queries, cached for
 * CACHE_SECONDS — no snapshot tables or scheduled jobs.
 */
final class Fleet
{
    public const CACHE_KEY = 'admin.system.fleet';

    public const CACHE_SECONDS = 600;

    /**
     * @return array{
     *     versions: list<array{version: ?string, build_profile: ?string, ios: int, android: int, devices: int, latest: bool, old: bool}>,
     *     platform: array{ios: int, android: int},
     *     push: array{on: int, off: int},
     *     old_build_users: int,
     * }
     */
    public static function summary(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => self::compute());
    }

    private static function compute(): array
    {
        $platform = self::appUserDevices()
            ->selectRaw('platform, COUNT(*) AS devices')
            ->groupBy('platform')
            ->toBase()
            ->pluck('devices', 'platform');

        $push = User::query()->appUsers()->has('devices')
            ->selectRaw('push_enabled, COUNT(*) AS users')
            ->groupBy('push_enabled')
            ->toBase()
            ->pluck('users', 'push_enabled');

        return [
            'versions' => self::versions(),
            'platform' => ['ios' => (int) ($platform['ios'] ?? 0), 'android' => (int) ($platform['android'] ?? 0)],
            'push' => ['on' => (int) ($push[1] ?? 0), 'off' => (int) ($push[0] ?? 0)],
            'old_build_users' => OldBuilds::constrain(User::query()->appUsers())->count(),
        ];
    }

    /**
     * @return list<array{version: ?string, build_profile: ?string, ios: int, android: int, devices: int, latest: bool, old: bool}>
     */
    private static function versions(): array
    {
        $old = OldBuilds::versions();
        $latest = OldBuilds::productionVersions()->first();

        return self::appUserDevices()
            ->selectRaw("app_version, build_profile, SUM(platform = 'ios') AS ios, SUM(platform = 'android') AS android, COUNT(*) AS devices")
            ->groupBy('app_version', 'build_profile')
            ->toBase()
            ->get()
            ->map(function (object $row) use ($old, $latest) {
                $production = $row->build_profile === OldBuilds::PRODUCTION;

                return [
                    'version' => $row->app_version,
                    'build_profile' => $row->build_profile,
                    'ios' => (int) $row->ios,
                    'android' => (int) $row->android,
                    'devices' => (int) $row->devices,
                    'latest' => $production && $row->app_version !== null && $row->app_version === $latest,
                    'old' => $production && $old->contains($row->app_version),
                ];
            })
            ->sort(fn (array $a, array $b) => match (true) {
                $a['version'] === $b['version'] => strcmp($a['build_profile'] ?? '', $b['build_profile'] ?? ''),
                $a['version'] === null => 1,
                $b['version'] === null => -1,
                default => version_compare($b['version'], $a['version']),
            })
            ->values()
            ->all();
    }

    /**
     * @return Builder<Device>
     */
    private static function appUserDevices(): Builder
    {
        return Device::query()->whereHas('user', fn (Builder $users) => $users->appUsers());
    }
}
