<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two roles: Admin (unrestricted, no Assigned Person needed) and User
     * (scoped to exactly one Assigned Person — another row in this same
     * table, whichever of them has is_staff = true). Requiredness of
     * assigned_person_id is enforced in UserRequest, not at the DB level,
     * the same way other conditional fields already work in this app.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 10)->default('user')->after('is_staff');
            $table->foreignId('assigned_person_id')->nullable()->after('role')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['assigned_person_id']);
            $table->dropColumn(['role', 'assigned_person_id']);
        });
    }
};
