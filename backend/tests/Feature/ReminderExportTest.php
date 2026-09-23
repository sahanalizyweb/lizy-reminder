<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Models\Reminder;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReminderExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-21 10:00:00');
        $this->seed(DatabaseSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function actingAsStaff(string $name = 'Lizy Reminder'): User
    {
        return tap(User::where('name', $name)->firstOrFail(), fn ($user) => Sanctum::actingAs($user));
    }

    /** @return array<int, array<int, mixed>> rows including the heading row */
    private function download(string $query = ''): array
    {
        $response = $this->get('/api/reminders/export'.($query ? "?$query" : ''))->assertOk();

        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertMatchesRegularExpression(
            '/attachment; filename=lizy-reminders-\d{4}-\d{2}-\d{2}-\d{6}\.xlsx/',
            $response->headers->get('Content-Disposition'),
        );

        ob_start();
        $response->baseResponse->sendContent();
        $bytes = ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $bytes);
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        return $sheet->toArray();
    }

    public function test_export_requires_authentication(): void
    {
        $this->getJson('/api/reminders/export')->assertUnauthorized();
    }

    public function test_export_with_no_filters_includes_every_reminder(): void
    {
        $this->actingAsStaff();

        $rows = $this->download();

        $this->assertSame([
            'ID', 'Reminder Type', 'Customer / Company', 'Phone',
            'Product Name', 'Product Category', 'Quantity', 'Price', 'Website Link',
            'Booking / Tour Name', 'Property / Project Name', 'Location',
            'Scheduled / Due Date', 'Reminder Date', 'Assigned To', 'Status', 'Days Overdue',
            'Notes', 'Created At',
        ], $rows[0]);

        // Header row + the 3 seeded reminders, none else created.
        $this->assertCount(4, $rows);
        $this->assertSame(3, Reminder::count());
    }

    public function test_export_respects_the_applied_filters(): void
    {
        $sahana = $this->actingAsStaff();
        $gokul = User::where('name', 'Gokul')->value('id');

        $match = Reminder::create([
            'reminder_type' => 'IT Service', 'customer_name' => 'Acme', 'phone' => '9876500001',
            'website_link' => 'https://acme.example.com', 'assigned_to' => $gokul,
            'scheduled_date' => '2026-09-25', 'reminder_date' => '2026-09-25',
        ]);
        Reminder::create([
            'reminder_type' => 'IT Service', 'customer_name' => 'Other Co', 'phone' => '9876500002',
            'website_link' => 'https://other.example.com', 'assigned_to' => $sahana->id,
            'scheduled_date' => '2026-09-25', 'reminder_date' => '2026-09-25',
        ]);
        Reminder::create([
            'reminder_type' => 'Product', 'customer_name' => 'Acme', 'phone' => '9876500003',
            'product_name' => 'Pump', 'product_category_id' => ProductCategory::where('name', 'Pump')->value('id'),
            'quantity' => 1, 'assigned_to' => $gokul,
            'scheduled_date' => '2026-09-25', 'reminder_date' => '2026-09-25',
        ]);

        $rows = $this->download("reminder_type=IT+Service&assigned_to=$gokul");

        // Header + exactly the one IT Service reminder assigned to Gokul.
        $this->assertCount(2, $rows);
        $this->assertEquals($match->id, $rows[1][0]);
        $this->assertSame('IT Service', $rows[1][1]);
        $this->assertSame('Gokul', $rows[1][14]);
    }

    public function test_export_search_and_status_filters_combine(): void
    {
        $this->actingAsStaff();

        Reminder::create([
            'reminder_type' => 'Product', 'customer_name' => 'Zed Traders', 'phone' => '9111111111',
            'product_name' => 'Openwell Pump', 'product_category_id' => ProductCategory::where('name', 'Pump')->value('id'),
            'quantity' => 1, 'scheduled_date' => '2026-09-30', 'reminder_date' => '2026-09-30',
        ]);

        $rows = $this->download('search=Zed&status=Upcoming');

        $this->assertCount(2, $rows);
        $this->assertSame('Zed Traders', $rows[1][2]);
        $this->assertSame('Upcoming', $rows[1][15]);
    }

    public function test_export_is_not_paginated(): void
    {
        $this->actingAsStaff();
        foreach (range(1, 20) as $i) {
            Reminder::create([
                'reminder_type' => 'Product', 'phone' => '9000000000', 'product_name' => "Item $i",
                'product_category_id' => ProductCategory::where('name', 'Other')->value('id'), 'quantity' => 1,
                'scheduled_date' => '2026-09-30', 'reminder_date' => '2026-09-30',
            ]);
        }

        // Default page size (15) would truncate the list API; export must not.
        $rows = $this->download();
        $this->assertCount(24, $rows); // heading + 3 seeded + 20 new
    }
}
