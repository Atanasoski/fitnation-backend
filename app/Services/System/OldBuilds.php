<?php

namespace App\Services\System;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The Old Build rule (CONTEXT.md), in one place: a Device on a production
 * build whose app version is older, by semantic version compare, than the
 * CURRENT_RELEASES newest production versions any Device reports. Preview and
 * development builds are never Old Builds, and neither is a Device with no
 * reported version.
 *
 * A user is on an Old Build when their most recently seen Device is (latest
 * last_seen_at, highest id breaking ties — as LocalHour reads it). The System
 * page's count and the Users list's `old_build=1` filter both go through
 * constrain(), so they cannot disagree.
 *
 * The versions are compared in PHP (version_compare) because SQL cannot order
 * "1.10.0" after "1.9.0"; the distinct production versions are a short list.
 */
final class OldBuilds
{
    public const PRODUCTION = 'production';

    /** How many of the newest production versions are still current. */
    public const CURRENT_RELEASES = 2;

    /**
     * Every production version any Device reports, newest first.
     *
     * @return Collection<int, string>
     */
    public static function productionVersions(): Collection
    {
        return Device::query()
            ->where('build_profile', self::PRODUCTION)
            ->whereNotNull('app_version')
            ->distinct()
            ->pluck('app_version')
            ->sort(fn (string $a, string $b) => version_compare($b, $a))
            ->values();
    }

    /**
     * The production versions that are Old Builds.
     *
     * @return Collection<int, string>
     */
    public static function versions(): Collection
    {
        $versions = self::productionVersions();
        $oldest = $versions->get(self::CURRENT_RELEASES - 1);

        return $oldest === null
            ? collect()
            : $versions->filter(fn (string $version) => version_compare($version, $oldest, '<'))->values();
    }

    /**
     * Narrow a users query to those whose most recently seen Device is an Old
     * Build.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function constrain(Builder $query): Builder
    {
        $old = self::versions();

        if ($old->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $latest = Device::query()->selectRaw(
            'user_id, app_version, build_profile, ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY last_seen_at DESC, id DESC) AS rank_in_user'
        );

        return $query->whereIn('users.id', Device::query()
            ->fromSub($latest, 'latest')
            ->where('rank_in_user', 1)
            ->where('build_profile', self::PRODUCTION)
            ->whereIn('app_version', $old->all())
            ->select('user_id'));
    }
}
