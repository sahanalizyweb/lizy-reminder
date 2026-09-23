<?php

namespace App\Http\Controllers;

use App\Models\NotificationRead;
use App\Models\Reminder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The notification bell: reminders whose Reminder Date is today, not yet
 * completed, and not yet marked read by the signed-in user. Nothing else —
 * no Overdue, no Upcoming, no Completed. Admin sees these across every
 * reminder; a User sees only reminders assigned to their linked Assigned
 * Person (same rule as everywhere else — see Reminder::visibleTo()).
 */
class NotificationController extends Controller
{
    /** The panel shows at most this many; `count` is never capped. */
    private const MAX_ITEMS = 15;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Reminder::today();

        $readIds = NotificationRead::where('user_id', $user->id)
            ->where('reminder_date', $today)
            ->pluck('reminder_id');

        $items = Reminder::query()
            ->with('assignee:id,name')
            ->visibleTo($user)
            ->open()
            ->where('reminder_date', $today)
            ->whereNotIn('id', $readIds)
            ->orderBy('id')
            ->get()
            ->map(fn (Reminder $r) => $this->present($r));

        return response()->json([
            'count' => $items->count(),
            'items' => $items->take(self::MAX_ITEMS)->values(),
        ]);
    }

    /**
     * Marks today's alert for this reminder as read for the signed-in user —
     * it disappears from their bell, but the reminder itself is untouched.
     * Idempotent: opening the same notification twice writes nothing extra.
     */
    public function markRead(Request $request, Reminder $reminder): JsonResponse
    {
        $this->authorize('view', $reminder);

        NotificationRead::firstOrCreate([
            'user_id' => $request->user()->id,
            'reminder_id' => $reminder->id,
            'reminder_date' => $reminder->reminder_date->toDateString(),
        ]);

        return response()->json(['message' => 'Marked as read.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Reminder $reminder): array
    {
        return [
            'id' => $reminder->id,
            'customer_name' => $reminder->customer_name,
            'label' => $reminder->product_name ?? $reminder->website_link ?? $reminder->booking_name ?? $reminder->property_name ?? $reminder->reminder_type,
            'reminder_date' => $reminder->reminder_date->toDateString(),
            'assigned_to_name' => $reminder->assignee?->name,
        ];
    }
}
