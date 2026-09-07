<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Support\StoredClock;
use Carbon\CarbonInterface;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * The Sent Record (CONTEXT.md), as a read: every notification of one kind
 * recorded for a set of users since an instant, grouped by user.
 *
 * The notifications table is the fact every scheduled rule dedupes against —
 * a rule runs every quarter hour and relies on this to send once. Which row
 * counts is each rule's own question (a matching step, a row since this
 * Monday), so the rows come back whole; what they share is the read: one
 * query, bounded below by the earliest instant any user in the batch is
 * measured from, since nothing older can matter to any of them.
 */
final class SentRecord
{
    /**
     * @param  Collection<int, Collection<int, DatabaseNotification>>  $byUser
     */
    private function __construct(private readonly Collection $byUser) {}

    /**
     * @param  class-string  $type  the notification class recorded
     * @param  list<int>  $userIds
     * @param  CarbonInterface  $earliest  inclusive; read on whatever clock it is written in
     */
    public static function since(string $type, array $userIds, CarbonInterface $earliest): self
    {
        if ($userIds === []) {
            return new self(collect());
        }

        return new self(
            DatabaseNotification::query()
                ->where('notifiable_type', User::class)
                ->whereIn('notifiable_id', $userIds)
                ->where('type', $type)
                ->where('created_at', '>=', StoredClock::bind($earliest))
                ->get(['notifiable_id', 'data', 'created_at'])
                ->groupBy('notifiable_id')
        );
    }

    /**
     * What was recorded for one user — empty, never missing, so a rule can
     * ask `->contains()` without a guard.
     *
     * @return Collection<int, DatabaseNotification>
     */
    public function for(int $userId): Collection
    {
        return $this->byUser->get($userId, collect());
    }
}
