<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Maps legacy theme keys (slate, rose, blue, green, amber, violet, teal, red)
     * to the 'default' theme in the users.settings JSON column.
     *
     * Idempotent: only updates rows where settings->theme is one of the legacy keys.
     * Rows with NULL settings, no theme key, 'default' theme, or a new valid theme
     * are not affected.
     */
    public function up(): void
    {
        $legacyKeys = ['slate', 'rose', 'blue', 'green', 'amber', 'violet', 'teal', 'red'];

        DB::table('users')
            ->whereNotNull('settings')
            ->whereIn('settings->theme', $legacyKeys)
            ->update(['settings' => DB::raw("JSON_SET(settings, '$.theme', 'default')")]);
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally a no-op: this is a forward-fix data migration.
     * The original theme values are discarded in the mapping.
     */
    public function down(): void
    {
        // No-op: legacy theme values cannot be recovered.
    }
};
