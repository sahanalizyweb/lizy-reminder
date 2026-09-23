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

/**
 * The notification bell: only reminders whose Reminder Date is today and are
 * not completed — no Overdue, Upcoming or Completed — and only those the
 * signed-in user hasn't already opened. Read status is stored in the
 * database (notification_reads), scoped per user, reminder and date.
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-21 10:00:00');
        $this->seed(DatabaseSeeder::class);
        // The 3 seeded reminders are due 23/09 and 29/09 — never "today" (21/09) in these
        // tests, so they never appear in notifications here regardless of role.
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function loginAs(string $name): User
    {
        return tap(User::where('name', $name)->firstOrFail(), fn ($user) => Sanctum::actingAs($user));
    }

    private function pumpCategory(): int
    {
        return ProductCategory::where('name', 'Pump')->value('id');
    }

    private function makeProduct(array $overrides = []): Reminder
    {
        return Reminder::create($overrides + [
            'reminder_type' => Reminder::TYPE_PRODUCT, 'customer_name' => 'Acme',
            'phone' => '9876543210', 'product_name' => 'Pump', 'product_category_id' => $this->pumpCategory(),
            'quantity' => 1, 'scheduled_date' => '2026-09-21', 'reminder_date' => '2026-09-21',
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
    }

    public function test_only_reminders_due_exactly_today_are_included(): void
    {
        $this->loginAs('Lizy Reminder');

        $today = $this->makeProduct(['customer_name' => "Today's Co", 'scheduled_date' => '2026-09-21', 'reminder_date' => '2026-09-21']);
        $overdue = $this->makeProduct(['scheduled_date' => '2026-09-18', 'reminder_date' => '2026-09-18']);
        $upcoming = $this->makeProduct(['scheduled_date' => '2026-09-25', 'reminder_date' => '2026-09-25']);
        $completedToday = $this->makeProduct(['scheduled_date' => '2026-09-21', 'reminder_date' => '2026-09-21', 'status' => 'Completed']);
        // Due date is today but the Reminder Date isn't: must NOT count (spec says Reminder Date, not Scheduled Date).
        $scheduledTodayOnly = $this->makeProduct(['scheduled_date' => '2026-09-21', 'reminder_date' => '2026-09-19']);

        $response = $this->getJson('/api/notifications')->assertOk();
        $this->assertSame(1, $response->json('count'));
        $ids = collect($response->json('items'))->pluck('id');
        $this->assertSame([$today->id], $ids->all());
        $this->assertFalse($ids->contains($overdue->id));
        $this->assertFalse($ids->contains($upcoming->id));
        $this->assertFalse($ids->contains($completedToday->id));
        $this->assertFalse($ids->contains($scheduledTodayOnly->id));
    }

    public function test_item_shape_has_customer_label_and_reminder_date(): void
    {
        $this->loginAs('Lizy Reminder');
        $reminder = $this->makeProduct(['customer_name' => 'Blue Bay', 'product_name' => 'Openwell Pump']);

        $item = $this->getJson('/api/notifications')->json('items.0');
        $this->assertSame($reminder->id, $item['id']);
        $this->assertSame('Blue Bay', $item['customer_name']);
        $this->assertSame('Openwell Pump', $item['label']);
        $this->assertSame('2026-09-21', $item['reminder_date']);
    }

    public function test_website_link_is_the_label_for_it_service(): void
    {
        $this->loginAs('Lizy Reminder');
        Reminder::create([
            'reminder_type' => 'IT Service', 'customer_name' => 'Acme', 'phone' => '9876543210',
            'website_link' => 'https://acme.example.com', 'scheduled_date' => '2026-09-21', 'reminder_date' => '2026-09-21',
        ]);

        $this->assertSame('https://acme.example.com', $this->getJson('/api/notifications')->json('items.0.label'));
    }

    public function test_a_user_only_sees_todays_reminders_for_their_own_person(): void
    {
        $sahana = User::where('name', 'Sahana')->value('id');
        $gokul = User::where('name', 'Gokul')->value('id');
        $mine = $this->makeProduct(['assigned_to' => $sahana]);
        $this->makeProduct(['assigned_to' => $gokul]);

        $this->loginAs('Sahana');

        $response = $this->getJson('/api/notifications')->assertOk();
        $this->assertSame(1, $response->json('count'));
        $this->assertSame($mine->id, $response->json('items.0.id'));
    }

    public function test_admin_sees_todays_reminders_for_every_staff_member(): void
    {
        $sahana = User::where('name', 'Sahana')->value('id');
        $gokul = User::where('name', 'Gokul')->value('id');
        $this->makeProduct(['assigned_to' => $sahana]);
        $this->makeProduct(['assigned_to' => $gokul]);
        $this->makeProduct(['assigned_to' => null]);

        $this->loginAs('Lizy Reminder');

        $this->assertSame(3, $this->getJson('/api/notifications')->json('count'));
    }

    public function test_opening_a_notification_marks_it_read_and_drops_the_count(): void
    {
        $this->loginAs('Lizy Reminder');
        $a = $this->makeProduct(['customer_name' => 'A']);
        $b = $this->makeProduct(['customer_name' => 'B']);
        $c = $this->makeProduct(['customer_name' => 'C']);

        $this->assertSame(3, $this->getJson('/api/notifications')->json('count'));

        $this->postJson("/api/notifications/{$a->id}/read")->assertOk();

        $response = $this->getJson('/api/notifications')->assertOk();
        $this->assertSame(2, $response->json('count'));
        $ids = collect($response->json('items'))->pluck('id');
        $this->assertFalse($ids->contains($a->id));
        $this->assertTrue($ids->contains($b->id));
        $this->assertTrue($ids->contains($c->id));
    }

    public function test_read_status_persists_across_separate_requests(): void
    {
        $reminder = $this->makeProduct();
        $this->loginAs('Lizy Reminder');
        $this->postJson("/api/notifications/{$reminder->id}/read")->assertOk();

        // A brand new authenticated context (simulating a fresh login after logout).
        Sanctum::actingAs(User::where('name', 'Lizy Reminder')->firstOrFail());

        $this->assertSame(0, $this->getJson('/api/notifications')->json('count'));
        $this->assertDatabaseHas('notification_reads', ['reminder_id' => $reminder->id, 'reminder_date' => '2026-09-21']);
    }

    public function test_marking_read_twice_is_harmless(): void
    {
        $this->loginAs('Lizy Reminder');
        $reminder = $this->makeProduct();

        $this->postJson("/api/notifications/{$reminder->id}/read")->assertOk();
        $this->postJson("/api/notifications/{$reminder->id}/read")->assertOk();

        $this->assertDatabaseCount('notification_reads', 1);
        $this->assertSame(0, $this->getJson('/api/notifications')->json('count'));
    }

    public function test_marking_read_does_not_change_the_reminder_itself(): void
    {
        $this->loginAs('Lizy Reminder');
        $reminder = $this->makeProduct();

        $this->postJson("/api/notifications/{$reminder->id}/read")->assertOk();

        $this->assertDatabaseHas('reminders', ['id' => $reminder->id, 'status' => Reminder::STATUS_PENDING]);
        $this->getJson("/api/reminders/{$reminder->id}")->assertOk()->assertJsonPath('data.status', 'Today');
    }

    public function test_a_user_cannot_mark_read_a_reminder_that_is_not_theirs(): void
    {
        $gokul = User::where('name', 'Gokul')->value('id');
        $theirs = $this->makeProduct(['assigned_to' => $gokul]);

        $this->loginAs('Sahana');

        $this->postJson("/api/notifications/{$theirs->id}/read")->assertForbidden();
        $this->assertDatabaseMissing('notification_reads', ['reminder_id' => $theirs->id]);
    }

    public function test_one_users_read_status_does_not_affect_another_users(): void
    {
        $sahana = $this->loginAs('Sahana');
        $shared = $this->makeProduct(['assigned_to' => $sahana->id]);
        $this->postJson("/api/notifications/{$shared->id}/read")->assertOk();
        $this->assertSame(0, $this->getJson('/api/notifications')->json('count'));

        $this->loginAs('Lizy Reminder');
        $this->assertSame(1, $this->getJson('/api/notifications')->json('count'));
    }

    public function test_postponing_a_read_reminder_and_it_becoming_today_again_shows_as_unread(): void
    {
        $this->loginAs('Lizy Reminder');
        $reminder = $this->makeProduct();
        $this->postJson("/api/notifications/{$reminder->id}/read")->assertOk();
        $this->assertSame(0, $this->getJson('/api/notifications')->json('count'));

        // Postponed to a future date: not in today's notifications at all right now.
        $reminder->update(['reminder_date' => '2026-09-24', 'scheduled_date' => '2026-09-24']);
        $this->assertSame(0, $this->getJson('/api/notifications')->json('count'));

        // Time passes; that new date is now today. The old read-mark (for the old date)
        // does not carry over — it is correctly unread again.
        Carbon::setTestNow('2026-09-24 10:00:00');
        $response = $this->getJson('/api/notifications')->assertOk();
        $this->assertSame(1, $response->json('count'));
        $this->assertSame($reminder->id, $response->json('items.0.id'));
    }

    public function test_count_is_never_capped_even_though_the_list_is(): void
    {
        $this->loginAs('Lizy Reminder');
        foreach (range(1, 20) as $i) {
            $this->makeProduct(['customer_name' => "Co $i"]);
        }

        $response = $this->getJson('/api/notifications')->assertOk();
        $this->assertSame(20, $response->json('count'));
        $this->assertCount(15, $response->json('items'));
    }
}
