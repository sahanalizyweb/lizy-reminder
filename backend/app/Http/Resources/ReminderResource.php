<?php

namespace App\Http\Resources;

use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reminder
 */
class ReminderResource extends JsonResource
{
    /**
     * `status` is the calculated state (Today / Upcoming / Overdue / Expired /
     * Completed) for most types, but for Travel / Real Estate — see
     * Reminder::MANUAL_STATUS_TYPES — it's simply whatever the Status dropdown
     * was last set to (one of Reminder::MANUAL_STATUSES); `is_completed`
     * reflects what is stored in the database either way. Every type's fields
     * are always present (null when not relevant to this reminder's type).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reminder_type' => $this->reminder_type,
            'customer_name' => $this->customer_name,
            'phone' => $this->phone,
            'product_name' => $this->product_name,
            'product_category_id' => $this->product_category_id,
            'product_category_name' => $this->category?->name,
            'quantity' => $this->quantity,
            'price' => $this->price !== null ? (float) $this->price : null,
            'website_link' => $this->website_link,
            'booking_name' => $this->booking_name,
            'property_name' => $this->property_name,
            'location' => $this->location,
            'scheduled_date' => $this->scheduled_date->toDateString(),
            'reminder_date' => $this->reminder_date->toDateString(),
            'assigned_to' => $this->assigned_to,
            'assigned_to_name' => $this->assignee?->name,
            'status' => $this->state(),
            'is_completed' => $this->isCompleted(),
            'days_overdue' => $this->daysOverdue(),
            'notes' => $this->notes,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
