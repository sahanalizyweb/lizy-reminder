<?php

namespace App\Services;

use App\Models\ProductCategory;
use App\Models\Reminder;
use App\Models\ReminderChange;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Writes Change History entries for a reminder. Creation is never logged —
 * call record() only after an update/complete has already been saved.
 */
class ReminderChangeLogger
{
    private const EMPTY = '—';

    /**
     * Compare `$before` (the reminder's attributes as they were immediately
     * before this save) against the reminder's current, already-saved state,
     * and write one ReminderChange per tracked field that actually changed.
     * Fields outside Reminder::TRACKED_FIELDS (id, timestamps, completed_at)
     * are ignored, and a field resubmitted with its existing value writes
     * nothing (Eloquent's getChanges() already excludes those).
     *
     * @param  array<string, mixed>  $before
     */
    public function record(Reminder $reminder, array $before, User $user): void
    {
        $changed = array_intersect_key($reminder->getChanges(), Reminder::TRACKED_FIELDS);

        foreach (array_keys($changed) as $field) {
            $previous = $this->describe($field, $before[$field] ?? null);
            $current = $this->describe($field, $reminder->getAttribute($field));

            if ($previous === $current) {
                continue;
            }

            ReminderChange::create([
                'reminder_id' => $reminder->id,
                'field' => $field,
                'field_label' => Reminder::TRACKED_FIELDS[$field],
                'previous_value' => $previous,
                'new_value' => $current,
                'changed_by_id' => $user->id,
                'changed_by_name' => $user->name,
            ]);
        }
    }

    /** The display string for a raw attribute value, resolved at write time. */
    private function describe(string $field, mixed $value): string
    {
        if ($field === 'assigned_to') {
            return $value ? (User::find($value)?->name ?? 'Unknown') : 'Unassigned';
        }

        if ($field === 'product_category_id') {
            return $value ? (ProductCategory::find($value)?->name ?? 'Unknown') : self::EMPTY;
        }

        if (in_array($field, ['scheduled_date', 'reminder_date'], true)) {
            return $value ? Carbon::parse($value)->toDateString() : self::EMPTY;
        }

        if ($value === null || $value === '') {
            return self::EMPTY;
        }

        return (string) $value;
    }
}
