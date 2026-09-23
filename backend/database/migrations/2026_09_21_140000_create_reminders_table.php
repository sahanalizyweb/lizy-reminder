<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Only Pending/Completed is stored in `status`. Today / Upcoming / Overdue /
     * Expired are calculated from the dates at query time (see App\Models\Reminder).
     */
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->string('reminder_type', 30);
            $table->string('customer_name')->default('Not Provided');
            $table->string('phone', 30);
            $table->string('product_service');
            $table->unsignedInteger('quantity')->default(1);
            $table->date('scheduled_date');
            $table->date('reminder_date');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('Pending');
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'reminder_date']);
            $table->index('scheduled_date');
            $table->index('reminder_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
