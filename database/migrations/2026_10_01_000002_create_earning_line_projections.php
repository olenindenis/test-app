<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Read models (projections) built from earning_line_events.
 * They can be dropped and rebuilt at any time: php artisan earning-lines:rebuild-projections
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('earning_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('employee_id')->index();
            $table->bigInteger('system_value_cents');
            $table->bigInteger('adjustments_total_cents')->default(0);
            // Set by the first manual adjustment; a non-null value means the line is locked.
            $table->timestampTz('locked_at', precision: 6)->nullable();
            $table->unsignedInteger('ignored_recalculations')->default(0);
        });

        Schema::create('earning_line_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('earning_line_id')->constrained('earning_lines')->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->bigInteger('amount_cents');
            $table->text('comment');
            $table->string('author_id');
            $table->timestampTz('added_at', precision: 6);

            $table->unique(['earning_line_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('earning_line_adjustments');
        Schema::dropIfExists('earning_lines');
    }
};
