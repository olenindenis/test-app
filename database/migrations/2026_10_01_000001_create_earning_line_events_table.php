<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('earning_line_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_id');
            $table->unsignedInteger('version');
            $table->string('event_type');
            $table->jsonb('payload');
            $table->timestampTz('occurred_at', precision: 6);
            $table->timestampTz('recorded_at', precision: 6)->useCurrent();

            $table->unique(['aggregate_id', 'version']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION earning_line_events_are_immutable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'earning_line_events is append-only: % is not allowed', TG_OP;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER earning_line_events_no_update_or_delete
                BEFORE UPDATE OR DELETE ON earning_line_events
                FOR EACH ROW EXECUTE FUNCTION earning_line_events_are_immutable();

            CREATE TRIGGER earning_line_events_no_truncate
                BEFORE TRUNCATE ON earning_line_events
                FOR EACH STATEMENT EXECUTE FUNCTION earning_line_events_are_immutable();
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('earning_line_events');
        DB::unprepared('DROP FUNCTION IF EXISTS earning_line_events_are_immutable()');
    }
};
