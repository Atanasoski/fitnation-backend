<?php

namespace App\Services;

use App\Models\User;
use App\Services\FitnessMetrics\StrengthBalance;
use App\Services\FitnessMetrics\StrengthScore;
use App\Services\FitnessMetrics\WeeklyProgress;

/**
 * The three fitness metrics, together, for one user.
 *
 * A façade and nothing else — it exists because GET /api/user/fitness-metrics
 * wants all three in one payload. The metrics themselves share no arithmetic
 * and live in App\Services\FitnessMetrics; anything that wants one of them
 * should ask that module directly rather than take the whole array and index
 * into it.
 *
 * Everything here is in Canonical Units; the resource formats (ADR-0001).
 *
 * Not final, unlike the modules under it: it is the seam a controller test
 * substitutes, which is the whole reason it is injected rather than new-ed.
 */
class FitnessMetricsService
{
    public function __construct(
        private StrengthScore $strengthScore,
        private StrengthBalance $strengthBalance,
        private WeeklyProgress $weeklyProgress,
    ) {}

    /**
     * @return array{strength_score: array<string, mixed>, strength_balance: array<string, mixed>, weekly_progress: array<string, mixed>}
     */
    public function getMetrics(User $user): array
    {
        $user->load('profile');

        return [
            'strength_score' => $this->strengthScore->for($user),
            'strength_balance' => $this->strengthBalance->for($user),
            'weekly_progress' => $this->weeklyProgress->for($user),
        ];
    }
}
