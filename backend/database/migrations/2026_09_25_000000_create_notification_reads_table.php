<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (user, reminder, date) a notification bell alert has been
     * opened for — persists across refresh/logout/login. Keyed by the date
     * being acknowledged (not just the reminder) so that if a reminder's
     * Reminder Date is later changed and eventually becomes "today" again,
     * it correctly shows as unread again rather than staying silently read
     * forever because of an old acknowledgement of a different date.
     */
    public function up(): void
    {
        Schema::create('notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reminder_id')->constrained('reminders')->cascadeOnDelete();
            $table->date('reminder_date');
            $table->timestamp('read_at')->useCurrent();

            $table->unique(['user_id', 'reminder_id', 'reminder_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_reads');
    }
};
