<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per field changed by an Edit/Update after a reminder was
     * created (creation itself is never logged). `previous_value` /
     * `new_value` and `changed_by_name` are display-ready snapshots taken at
     * the moment of the change, so the history stays accurate even if the
     * assigned person, category or user is later renamed or removed.
     */
    public function up(): void
    {
        Schema::create('reminder_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reminder_id')->constrained('reminders')->cascadeOnDelete();
            $table->string('field', 40);
            $table->string('field_label', 60);
            $table->text('previous_value')->nullable();
            $table->text('new_value')->nullable();
            $table->foreignId('changed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_name');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['reminder_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_changes');
    }
};
