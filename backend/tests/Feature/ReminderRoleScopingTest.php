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
 * Admin sees/manages every reminder. A User sees/manages only reminders
 * assigned to their linked Assigned Person, and can never move a reminder
 * to anyone else.
 */
class ReminderRoleScopingTest extends TestCase
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
            'quantity' => 1, 'scheduled_date' => '2026-09-25', 'reminder_date' => '2026-09-25',
        ]);
    }

    public function test_seeded_staff_accounts_are_users_scoped_to_themselves(): void
    {
        $sahana = User::where('name', 'Sahana')->firstOrFail();
        $this->assertSame(User::ROLE_USER, $sahana->role);
        $this->assertSame($sahana->id, $sahana->assigned_person_id);

        $admin = User::where('name', 'Lizy Reminder')->firstOrFail();
        $this->assertSame(User::ROLE_ADMIN, $admin->role);
        $this->assertNull($admin->assigned_person_id);
    }

    public function test_login_and_me_report_role_and_assigned_person(): void
    {
        $this->postJson('/api/login', ['email' => 'sahana@lizyweb.in', 'password' => 'Lizy@2026'])
            ->assertOk()
            ->assertJsonPath('user.role', 'user')
            ->assertJsonPath('user.assigned_person_name', 'Sahana');

        $this->postJson('/api/login', ['email' => 'omsreminder@gmail.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('user.assigned_person_id', null);
    }

    public function test_a_user_sees_only_reminders_assigned_to_their_own_person(): void
    {
        $sahana = User::where('name', 'Sahana')->value('id');
        $gokul = User::where('name', 'Gokul')->value('id');

        $mine = $this->makeProduct(['assigned_to' => $sahana]);
        $this->makeProduct(['assigned_to' => $gokul]);
        $this->makeProduct(['assigned_to' => null]); // unassigned: not "mine" either

        $this->loginAs('Sahana');

        $ids = collect($this->getJson('/api/reminders')->assertOk()->json('data'))->pluck('id');
        $this->assertSame([$mine->id], $ids->all());

        // Asking for someone else's / all / unassigned reminders is ignored — always scoped to Sahana.
        $ids = collect($this->getJson("/api/reminders?assigned_to=$gokul")->assertOk()->json('data'))->pluck('id');
        $this->assertSame([$mine->id], $ids->all());
        $ids = collect($this->getJson('/api/reminders?assigned_to=unassigned')->assertOk()->json('data'))->pluck('id');
        $this->assertSame([$mine->id], $ids->all());
    }

    public function test_a_user_summary_counts_are_scoped_too(): void
    {
        $sahana = User::where('name', 'Sahana')->value('id');
        $gokul = User::where('name', 'Gokul')->value('id');

        $this->makeProduct(['assigned_to' => $sahana, 'scheduled_date' => '2026-09-21', 'reminder_date' => '2026-09-21']);
        $this->makeProduct(['assigned_to' => $gokul, 'scheduled_date' => '2026-09-21', 'reminder_date' => '2026-09-21']);

        $this->loginAs('Sahana');

        $this->getJson('/api/reminders/summary')->assertOk()->assertJson(['today' => 1]);
    }

    public function test_an_admin_is_not_scoped(): void
    {
        $sahana = User::where('name', 'Sahana')->value('id');
        $gokul = User::where('name', 'Gokul')->value('id');
        $this->makeProduct(['assigned_to' => $sahana]);
        $this->makeProduct(['assigned_to' => $gokul]);
        $this->makeProduct(['assigned_to' => null]);

        $this->loginAs('Lizy Reminder');

        // 3 seeded + 3 new.
        $this->assertCount(6, $this->getJson('/api/reminders')->assertOk()->json('data'));
    }

    public function test_a_user_cannot_view_edit_complete_or_delete_someone_elses_reminder(): void
    {
        $gokul = User::where('name', 'Gokul')->value('id');
        $theirs = $this->makeProduct(['assigned_to' => $gokul]);

        $this->loginAs('Sahana');

        $this->getJson("/api/reminders/{$theirs->id}")->assertForbidden();
        $this->getJson("/api/reminders/{$theirs->id}/history")->assertForbidden();
        $this->putJson("/api/reminders/{$theirs->id}", [
            'reminder_type' => 'Product', 'phone' => '9876543210', 'product_name' => 'Pump',
            'product_category_id' => $this->pumpCategory(), 'quantity' => 1,
            'scheduled_date' => '2026-09-25', 'reminder_date' => '2026-09-25',
        ])->assertForbidden();
        $this->postJson("/api/reminders/{$theirs->id}/complete")->assertForbidden();
        $this->deleteJson("/api/reminders/{$theirs->id}")->assertForbidden();

        $this->assertDatabaseHas('reminders', ['id' => $theirs->id, 'status' => Reminder::STATUS_PENDING]);
    }

    public function test_a_user_can_view_edit_complete_and_delete_their_own_reminder(): void
    {
        $sahana = User::where('name', 'Sahana')->value('id');
        $mine = $this->makeProduct(['assigned_to' => $sahana]);

        $this->loginAs('Sahana');

        $this->getJson("/api/reminders/{$mine->id}")->assertOk();
        $this->putJson("/api/reminders/{$mine->id}", [
            'reminder_type' => 'Product', 'phone' => '9876543210', 'product_name' => 'Submersible Pump',
            'product_category_id' => $this->pumpCategory(), 'quantity' => 2,
            'scheduled_date' => '2026-09-25', 'reminder_date' => '2026-09-25',
        ])->assertOk()->assertJsonPath('data.product_name', 'Submersible Pump');
        $this->postJson("/api/reminders/{$mine->id}/complete")->assertOk()->assertJsonPath('data.status', 'Completed');
        $this->deleteJson("/api/reminders/{$mine->id}")->assertNoContent();
    }

    public function test_a_users_new_reminder_is_always_assigned_to_themselves(): void
    {
        $gokul = User::where('name', 'Gokul')->value('id');
        $this->loginAs('Sahana');
        $sahana = User::where('name', 'Sahana')->value('id');

        $data = $this->postJson('/api/reminders', [
            'reminder_type' => 'Product', 'phone' => '9876543210', 'product_name' => 'Pump',
            'product_category_id' => $this->pumpCategory(), 'quantity' => 1,
            'assigned_to' => $gokul, // tries to hand it to Gokul instead
            'scheduled_date' => '2026-09-25', 'reminder_date' => '2026-09-25',
        ])->assertCreated()->json('data');

        $this->assertSame($sahana, $data['assigned_to']);
        $this->assertSame('Sahana', $data['assigned_to_name']);
    }

    public function test_a_user_cannot_reassign_their_own_reminder_to_someone_else(): void
    {
        $sahana = User::where('name', 'Sahana')->value('id');
        $gokul = User::where('name', 'Gokul')->value('id');
        $mine = $this->makeProduct(['assigned_to' => $sahana]);

        $this->loginAs('Sahana');

        $data = $this->putJson("/api/reminders/{$mine->id}", [
            'reminder_type' => 'Product', 'phone' => '9876543210', 'product_name' => 'Pump',
            'product_category_id' => $this->pumpCategory(), 'quantity' => 1,
            'assigned_to' => $gokul, // tries to hand it off
            'scheduled_date' => '2026-09-25', 'reminder_date' => '2026-09-25',
        ])->assertOk()->json('data');

        $this->assertSame($sahana, $data['assigned_to']);
        // Handing it off is not a real change (it was forced back), so no history entry for it either.
        $entries = collect($this->getJson("/api/reminders/{$mine->id}/history")->json('data'));
        $this->assertFalse($entries->contains('field', 'assigned_to'));
    }

    public function test_a_users_export_is_scoped_too(): void
    {
        $sahana = User::where('name', 'Sahana')->value('id');
        $gokul = User::where('name', 'Gokul')->value('id');
        $this->makeProduct(['assigned_to' => $sahana, 'product_name' => 'Mine']);
        $this->makeProduct(['assigned_to' => $gokul, 'product_name' => 'NotMine']);

        $this->loginAs('Sahana');

        $response = $this->get('/api/reminders/export')->assertOk();
        ob_start();
        $response->baseResponse->sendContent();
        $bytes = ob_get_clean();

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $bytes);
        $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet()->toArray();
        unlink($path);

        $productNames = collect($rows)->skip(1)->pluck(4); // "Product Name" is column index 4
        $this->assertContains('Mine', $productNames);
        $this->assertNotContains('NotMine', $productNames);
    }
}
