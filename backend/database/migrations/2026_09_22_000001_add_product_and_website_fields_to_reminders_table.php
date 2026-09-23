<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The app now has two reminder types: Product (product_name, category,
     * quantity, price) and IT Service (website_link). `product_service`
     * becomes `product_name`; existing data is kept, just renamed. All the
     * new/renamed columns are nullable because a given reminder only uses
     * one set of them.
     */
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->renameColumn('product_service', 'product_name');
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->string('product_name')->nullable()->change();
            $table->foreignId('product_category_id')->nullable()->after('product_name')
                ->constrained('product_categories')->nullOnDelete();
            $table->decimal('price', 12, 2)->nullable()->after('quantity');
            $table->string('website_link', 2048)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $table) {
            $table->dropForeign(['product_category_id']);
            $table->dropColumn(['product_category_id', 'price', 'website_link']);
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->renameColumn('product_name', 'product_service');
        });
    }
};
