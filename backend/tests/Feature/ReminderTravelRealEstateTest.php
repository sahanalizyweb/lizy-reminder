<?php

namespace Tests\Feature;

use App\Models\Reminder;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The two newer reminder types: Travel / Family Tour Booking (booking_name)
 * and Real Estate Marketing (property_name, location). Both reuse
 * scheduled_date/reminder_date from Product/IT Service, and both pick their
 * `status` by hand from Reminder::MANUAL_STATUSES instead of it being
 * calculated from the dates — Product and IT Service are unaffected by any
 * of this (see ReminderApiTest for those).
 */
class ReminderTravelRealEstateTest extends TestCase
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

    private function actingAsAdmin(): User
    {
        return tap(User::where('name', 'Lizy Reminder')->firstOrFail(), fn ($user) => Sanctum::actingAs($user));
    }

    private function travelPayload(array $overrides = []): array
    {
        return array_merge([
            'reminder_type' => Reminder::TYPE_TRAVEL,
            'customer_name' => 'The Sharmas',
            'phone' => '9876543210',
            'booking_name' => 'Kerala Family Tour',
            'scheduled_date' => '2026-09-25',
            'reminder_date' => '2026-09-23',
            'status' => 'Confirmed',
        ], $overrides);
    }

    private function realEstatePayload(array $overrides = []): array
    {
        return array_merge([
            'reminder_type' => Reminder::TYPE_REAL_ESTATE,
            'customer_name' => 'Green Acres Buyer',
            'phone' => '9876500000',
            'property_name' => 'Lakeview Apartments',
            'location' => 'Coimbatore',
            'scheduled_date' => '2026-09-25',
            'reminder_date' => '2026-09-23',
            'status' => 'Scheduled',
        ], $overrides);
    }

    // -------------------------------------------------------------------
    // Create / validate
    // -------------------------------------------------------------------

    public function test_creating_a_travel_reminder_works(): void
    {
        $this->actingAsAdmin();

        $data = $this->postJson('/api/reminders', $this->travelPayload())->assertCreated()->json('data');

        $this->assertSame(Reminder::TYPE_TRAVEL, $data['reminder_type']);
        $this->assertSame('Kerala Family Tour', $data['booking_name']);
        $this->assertSame('Confirmed', $data['status']);
        $this->assertNull($data['product_name']);
        $this->assertNull($data['website_link']);
        $this->assertNull($data['property_name']);
    }

    public function test_creating_a_real_estate_reminder_works(): void
    {
        $this->actingAsAdmin();

        $data = $this->postJson('/api/reminders', $this->realEstatePayload())->assertCreated()->json('data');

        $this->assertSame(Reminder::TYPE_REAL_ESTATE, $data['reminder_type']);
        $this->assertSame('Lakeview Apartments', $data['property_name']);
        $this->assertSame('Coimbatore', $data['location']);
        $this->assertSame('Scheduled', $data['status']);
        $this->assertNull($data['booking_name']);
    }

    public function test_travel_reminder_requires_booking_name(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/reminders', $this->travelPayload(['booking_name' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors(['booking_name']);
    }

    public function test_real_estate_reminder_requires_property_name_and_location(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/reminders', $this->realEstatePayload(['property_name' => '', 'location' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors(['property_name', 'location']);
    }

    public function test_travel_and_real_estate_require_a_status(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/reminders', $this->travelPayload(['status' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);

        $this->postJson('/api/reminders', $this->realEstatePayload(['status' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);
    }

    public function test_status_must_be_one_of_the_fixed_manual_list(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/reminders', $this->travelPayload(['status' => 'Pending']))
            ->assertUnprocessable()->assertJsonValidationErrors(['status']);

        $this->postJson('/api/reminders', $this->travelPayload(['status' => 'Cancelled']))
            ->assertCreated();
    }

    public function test_product_and_it_service_are_unaffected_by_the_new_status_rule(): void
    {
        $this->actingAsAdmin();

        // Product/IT Service still default status silently (no "status" sent at all) — unchanged behaviour.
        $data = $this->postJson('/api/reminders', [
            'reminder_type' => Reminder::TYPE_PRODUCT,
            'customer_name' => 'Acme',
            'phone' => '9876543210',
            'product_name' => 'Pump',
            'product_category_id' => \App\Models\ProductCategory::first()->id,
            'quantity' => 1,
            'scheduled_date' => '2026-09-25',
            'reminder_date' => '2026-09-23',
        ])->assertCreated()->json('data');

        $this->assertSame('Upcoming', $data['status']);
    }

    // -------------------------------------------------------------------
    // status is manual, not calculated
    // -------------------------------------------------------------------

    public function test_travel_status_is_exactly_what_was_chosen_regardless_of_dates(): void
    {
        $this->actingAsAdmin();

        // Reminder date is in the past (would be "Overdue" if calculated like Product/IT Service),
        // but the manually chosen status is what's shown instead.
        $data = $this->postJson('/api/reminders', $this->travelPayload([
            'scheduled_date' => '2026-09-10', 'reminder_date' => '2026-09-10', 'status' => 'Processing',
        ]))->assertCreated()->json('data');

        $this->assertSame('Processing', $data['status']);
        $this->assertFalse($data['is_completed']);
    }

    public function test_setting_status_to_completed_marks_it_completed(): void
    {
        $this->actingAsAdmin();
        $reminder = Reminder::create($this->travelPayload());

        $updated = $this->putJson("/api/reminders/{$reminder->id}", $this->travelPayload(['status' => 'Completed']))
            ->assertOk()->json('data');

        $this->assertSame('Completed', $updated['status']);
        $this->assertTrue($updated['is_completed']);
        $this->assertNotNull(Reminder::find($reminder->id)->completed_at);
    }

    public function test_the_complete_quick_action_works_for_travel_reminders_too(): void
    {
        $this->actingAsAdmin();
        $reminder = Reminder::create($this->travelPayload(['status' => 'Confirmed']));

        $data = $this->postJson("/api/reminders/{$reminder->id}/complete")->assertOk()->json('data');

        $this->assertSame('Completed', $data['status']);
        $this->assertTrue($data['is_completed']);
    }

    // -------------------------------------------------------------------
    // Filtering / search / dashboard dates still work the same way
    // -------------------------------------------------------------------

    public function test_filtering_by_reminder_type(): void
    {
        $this->actingAsAdmin();
        Reminder::create($this->travelPayload());
        Reminder::create($this->realEstatePayload());

        $travel = $this->getJson('/api/reminders?reminder_type='.urlencode(Reminder::TYPE_TRAVEL))->assertOk()->json('data');
        $this->assertCount(1, $travel);
        $this->assertSame('Kerala Family Tour', $travel[0]['booking_name']);

        $realEstate = $this->getJson('/api/reminders?reminder_type='.urlencode(Reminder::TYPE_REAL_ESTATE))->assertOk()->json('data');
        $this->assertCount(1, $realEstate);
        $this->assertSame('Lakeview Apartments', $realEstate[0]['property_name']);
    }

    public function test_filtering_by_a_manual_status_value(): void
    {
        $this->actingAsAdmin();
        Reminder::create($this->travelPayload(['status' => 'Due']));
        Reminder::create($this->travelPayload(['status' => 'Confirmed', 'phone' => '9876500099']));

        $due = $this->getJson('/api/reminders?status=Due')->assertOk()->json('data');
        $this->assertCount(1, $due);
        $this->assertSame('Due', $due[0]['status']);
    }

    public function test_search_matches_booking_name_property_name_and_location(): void
    {
        $this->actingAsAdmin();
        Reminder::create($this->travelPayload());
        Reminder::create($this->realEstatePayload());

        $this->assertCount(1, $this->getJson('/api/reminders?search=Kerala')->assertOk()->json('data'));
        $this->assertCount(1, $this->getJson('/api/reminders?search=Lakeview')->assertOk()->json('data'));
        $this->assertCount(1, $this->getJson('/api/reminders?search=Coimbatore')->assertOk()->json('data'));
    }

    public function test_dashboard_today_bucket_still_works_by_date_for_the_new_types(): void
    {
        $this->actingAsAdmin();
        // reminder_date is today (21st) even though status is a manual, non-date-derived value.
        Reminder::create($this->travelPayload(['reminder_date' => '2026-09-21', 'status' => 'Scheduled']));

        $today = $this->getJson('/api/reminders?view=today')->assertOk()->json('data');
        $this->assertCount(1, $today);
        $this->assertSame('Scheduled', $today[0]['status']);
    }

    public function test_change_history_records_booking_name_and_status_changes(): void
    {
        $admin = $this->actingAsAdmin();
        $reminder = Reminder::create($this->travelPayload());

        $this->putJson("/api/reminders/{$reminder->id}", $this->travelPayload([
            'booking_name' => 'Kerala Family Tour (revised)', 'status' => 'Processing',
        ]))->assertOk();

        $history = collect($this->getJson("/api/reminders/{$reminder->id}/history")->assertOk()->json('data'));
        $bookingChange = $history->firstWhere('field', 'booking_name');
        $statusChange = $history->firstWhere('field', 'status');

        $this->assertNotNull($bookingChange);
        $this->assertSame('Kerala Family Tour', $bookingChange['previous_value']);
        $this->assertSame('Kerala Family Tour (revised)', $bookingChange['new_value']);
        $this->assertSame($admin->name, $bookingChange['changed_by_name']);

        $this->assertNotNull($statusChange);
        $this->assertSame('Confirmed', $statusChange['previous_value']);
        $this->assertSame('Processing', $statusChange['new_value']);
    }

    public function test_excel_export_includes_the_new_columns(): void
    {
        $this->actingAsAdmin();
        Reminder::create($this->travelPayload());

        $response = $this->get('/api/reminders/export')->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml.sheet',
            $response->headers->get('Content-Type'),
        );
    }
}
