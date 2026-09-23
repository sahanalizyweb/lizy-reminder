<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Models\Reminder;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReminderChangeHistoryTest extends TestCase
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

    private function history(int $id): array
    {
        return $this->getJson("/api/reminders/$id/history")->assertOk()->json('data');
    }

    public function test_creating_a_reminder_writes_no_history(): void
    {
        $sahana = $this->actingAsStaff();

        $id = $this->postJson('/api/reminders', [
            'reminder_type' => 'Product', 'customer_name' => 'Acme', 'phone' => '9876543210',
            'product_name' => 'Pump', 'product_category_id' => ProductCategory::where('name', 'Pump')->value('id'),
            'quantity' => 1, 'assigned_to' => $sahana->id,
            'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-09-28',
        ])->assertCreated()->json('data.id');

        $this->assertSame([], $this->history($id));
        // The 3 seeded reminders were also "created", never edited: no history either.
        $seeded = Reminder::where('id', '!=', $id)->value('id');
        $this->assertSame([], $this->history($seeded));
    }

    public function test_editing_writes_one_entry_per_changed_field_only(): void
    {
        $sahana = $this->actingAsStaff();
        $gokul = User::where('name', 'Gokul')->value('id');
        $motor = ProductCategory::where('name', 'Motor')->value('id');

        $reminder = Reminder::create([
            'reminder_type' => 'Product', 'customer_name' => 'Acme', 'phone' => '9876543210',
            'product_name' => 'Pump', 'product_category_id' => $this->pumpCategory(),
            'quantity' => 1, 'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-09-28',
        ]);

        // Same phone/quantity resubmitted (unchanged) + assigned_to and reminder_date genuinely changed.
        $this->putJson("/api/reminders/{$reminder->id}", [
            'reminder_type' => 'Product', 'customer_name' => 'Acme', 'phone' => '9876543210',
            'product_name' => 'Pump', 'product_category_id' => $this->pumpCategory(),
            'quantity' => 1, 'assigned_to' => $gokul,
            'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-09-30',
        ])->assertOk();

        $entries = $this->history($reminder->id);
        $this->assertCount(2, $entries);

        $byField = collect($entries)->keyBy('field');
        $this->assertSame('Unassigned', $byField['assigned_to']['previous_value']);
        $this->assertSame('Gokul', $byField['assigned_to']['new_value']);
        $this->assertSame('Assigned Person', $byField['assigned_to']['field_label']);
        $this->assertSame('Lizy Reminder', $byField['assigned_to']['changed_by_name']);

        $this->assertSame('2026-09-28', $byField['reminder_date']['previous_value']);
        $this->assertSame('2026-09-30', $byField['reminder_date']['new_value']);

        // A second edit that changes the category adds one more entry; history keeps growing, newest first.
        $this->putJson("/api/reminders/{$reminder->id}", [
            'reminder_type' => 'Product', 'customer_name' => 'Acme', 'phone' => '9876543210',
            'product_name' => 'Pump', 'product_category_id' => $motor,
            'quantity' => 1, 'assigned_to' => $gokul,
            'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-09-30',
        ])->assertOk();

        $entries = $this->history($reminder->id);
        $this->assertCount(3, $entries);
        $this->assertSame('product_category_id', $entries[0]['field']);
        $this->assertSame('Pump', $entries[0]['previous_value']);
        $this->assertSame('Motor', $entries[0]['new_value']);
        // Newest first: the category change (just made) precedes the earlier assigned_to/date entries.
        $this->assertSame('assigned_to', $entries[1]['field']);
    }

    public function test_no_op_edit_writes_nothing(): void
    {
        $this->actingAsStaff();
        $reminder = Reminder::create([
            'reminder_type' => 'IT Service', 'customer_name' => 'Acme', 'phone' => '9876543210',
            'website_link' => 'https://example.com', 'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-09-28',
        ]);

        $this->putJson("/api/reminders/{$reminder->id}", [
            'reminder_type' => 'IT Service', 'customer_name' => 'Acme', 'phone' => '9876543210',
            'website_link' => 'https://example.com', 'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-09-28',
        ])->assertOk();

        $this->assertSame([], $this->history($reminder->id));
    }

    public function test_completing_writes_a_status_entry(): void
    {
        $sahana = $this->actingAsStaff();
        $reminder = Reminder::create([
            'reminder_type' => 'IT Service', 'customer_name' => 'Acme', 'phone' => '9876543210',
            'website_link' => 'https://example.com', 'scheduled_date' => '2026-09-21', 'reminder_date' => '2026-09-21',
        ]);

        $this->postJson("/api/reminders/{$reminder->id}/complete")->assertOk();

        $entries = $this->history($reminder->id);
        $this->assertCount(1, $entries);
        $this->assertSame('status', $entries[0]['field']);
        $this->assertSame('Pending', $entries[0]['previous_value']);
        $this->assertSame('Completed', $entries[0]['new_value']);
        $this->assertSame('Lizy Reminder', $entries[0]['changed_by_name']);

        // Completing an already-completed reminder writes nothing further.
        $this->postJson("/api/reminders/{$reminder->id}/complete")->assertOk();
        $this->assertCount(1, $this->history($reminder->id));
    }

    public function test_notes_and_multiple_edits_apply_to_it_service_too(): void
    {
        $this->actingAsStaff();
        $reminder = Reminder::create([
            'reminder_type' => 'IT Service', 'customer_name' => 'Acme', 'phone' => '9876543210',
            'website_link' => 'https://example.com', 'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-09-28',
            'notes' => 'Check website',
        ]);

        $this->putJson("/api/reminders/{$reminder->id}", [
            'reminder_type' => 'IT Service', 'customer_name' => 'Acme', 'phone' => '9876543210',
            'website_link' => 'https://example.org', 'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-09-28',
            'notes' => 'Follow up with customer',
        ])->assertOk();

        $byField = collect($this->history($reminder->id))->keyBy('field');
        $this->assertSame('https://example.com', $byField['website_link']['previous_value']);
        $this->assertSame('https://example.org', $byField['website_link']['new_value']);
        $this->assertSame('Check website', $byField['notes']['previous_value']);
        $this->assertSame('Follow up with customer', $byField['notes']['new_value']);
    }

    public function test_deleting_a_reminder_deletes_its_history(): void
    {
        $this->actingAsStaff();
        $reminder = Reminder::create([
            'reminder_type' => 'Product', 'phone' => '9876543210', 'product_name' => 'Pump',
            'product_category_id' => $this->pumpCategory(), 'quantity' => 1,
            'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-09-28',
        ]);
        $this->putJson("/api/reminders/{$reminder->id}", [
            'reminder_type' => 'Product', 'phone' => '9876543210', 'product_name' => 'Submersible Pump',
            'product_category_id' => $this->pumpCategory(), 'quantity' => 1,
            'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-09-28',
        ])->assertOk();
        $this->assertDatabaseCount('reminder_changes', 1);

        $this->deleteJson("/api/reminders/{$reminder->id}")->assertNoContent();

        $this->assertDatabaseCount('reminder_changes', 0);
    }

    public function test_history_requires_authentication(): void
    {
        $reminder = Reminder::create([
            'reminder_type' => 'Product', 'phone' => '9876543210', 'product_name' => 'Pump',
            'product_category_id' => $this->pumpCategory(), 'quantity' => 1,
            'scheduled_date' => '2026-10-01', 'reminder_date' => '2026-09-28',
        ]);

        $this->getJson("/api/reminders/{$reminder->id}/history")->assertUnauthorized();
    }

    private function pumpCategory(): int
    {
        return ProductCategory::where('name', 'Pump')->value('id');
    }
}
