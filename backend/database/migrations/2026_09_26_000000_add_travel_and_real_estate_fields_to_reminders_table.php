<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two more reminder types: Travel / Family Tour Booking (booking_name)
     * and Real Estate Marketing (property_name, location). Both reuse the
     * existing scheduled_date/reminder_date columns — scheduled_date is
     * simply labelled "Travel Date" or "Marketing Follow-up Date" on their
     * forms, the same way it's already shared, under one label, by Product
     * and IT Service. All three new columns are nullable since a given
     * reminder only uses the set that matches its own type.
     */
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->string('booking_name')->nullable()->after('website_link');
            $table->string('property_name')->nullable()->after('booking_name');
            $table->string('location')->nullable()->after('property_name');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropColumn(['booking_name', 'property_name', 'location']);
        });
    }
};
