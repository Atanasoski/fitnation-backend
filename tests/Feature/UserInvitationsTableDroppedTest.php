<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Spec 025 ticket 03 drops `user_invitations`; rolling the migration back
 * recreates it as the original create migration did. Schema changes commit
 * MySQL's open transaction, so this class runs outside one and leaves the
 * table dropped again when done.
 */
class UserInvitationsTableDroppedTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_10_07_000001_drop_user_invitations_table.php';

    /**
     * @return array<int, string|null>
     */
    protected function connectionsToTransact(): array
    {
        return [];
    }

    public function test_the_table_does_not_exist_after_migrating(): void
    {
        $this->assertFalse(Schema::hasTable('user_invitations'));
    }

    public function test_rolling_the_migration_back_recreates_the_table(): void
    {
        $migration = require base_path(self::MIGRATION);

        try {
            $migration->down();

            $this->assertTrue(Schema::hasTable('user_invitations'));
            $this->assertSame(
                ['accepted_at', 'created_at', 'email', 'expires_at', 'id', 'invited_by', 'partner_id', 'token', 'updated_at'],
                collect(Schema::getColumnListing('user_invitations'))->sort()->values()->all(),
            );
        } finally {
            $migration->up();
        }

        $this->assertFalse(Schema::hasTable('user_invitations'));
    }
}
