<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Postgres only. Two holding bookings (pending, confirmed, checked_in) of one
     * unit may not overlap on [check_in, check_out), and every stay is at least
     * one night. SQLite has no exclusion constraints, so nothing is added there.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_check_out_after_check_in CHECK (check_out > check_in)');

        DB::statement(<<<'SQL'
            ALTER TABLE bookings ADD CONSTRAINT bookings_unit_dates_no_overlap
            EXCLUDE USING gist (unit_id WITH =, daterange(check_in, check_out, '[)') WITH &&)
            WHERE (status IN ('pending', 'confirmed', 'checked_in'))
            SQL);
    }

    /**
     * The btree_gist extension is left installed.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_unit_dates_no_overlap');
        DB::statement('ALTER TABLE bookings DROP CONSTRAINT IF EXISTS bookings_check_out_after_check_in');
    }
};
