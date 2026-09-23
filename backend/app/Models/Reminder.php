<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A reminder is either Pending or Completed in the database. The state that
 * people work with (Today / Upcoming / Overdue / Expired) is always derived
 * from the dates and today's date:
 *
 *   Completed  status = Completed
 *   Today      reminder_date or scheduled_date is today
 *   Upcoming   reminder_date is after today
 *   Overdue    reminder_date has passed (and scheduled_date is not today)
 *   Expired    like Overdue, but the scheduled/due date itself has passed on a
 *              renewal-style reminder (legacy types only, see RENEWAL_TYPES)
 *
 * The scopes below and state() implement exactly the same rules — except
 * for Travel / Family Tour Booking and Real Estate Marketing (see
 * MANUAL_STATUS_TYPES below), whose status is never calculated: it is
 * whatever the Status dropdown on their form was last set to.
 *
 * Four reminder types can be created or edited into: Product, IT Service,
 * Travel / Family Tour Booking and Real Estate Marketing (TYPES). Older
 * reminders may still carry a type from before (e.g. Hosting) — those keep
 * working (RENEWAL_TYPES still drives their Expired calculation) but that
 * type can no longer be chosen anywhere in the app.
 */
#[Fillable([
    'reminder_type', 'customer_name', 'phone', 'assigned_to', 'status', 'notes', 'completed_at',
    'product_name', 'product_category_id', 'quantity', 'price',
    'website_link',
    'booking_name',
    'property_name', 'location',
    'scheduled_date', 'reminder_date',
])]
class Reminder extends Model
{
    public const TYPE_PRODUCT = 'Product';

    public const TYPE_IT_SERVICE = 'IT Service';

    public const TYPE_TRAVEL = 'Travel / Family Tour Booking';

    public const TYPE_REAL_ESTATE = 'Real Estate Marketing';

    public const TYPES = [self::TYPE_PRODUCT, self::TYPE_IT_SERVICE, self::TYPE_TRAVEL, self::TYPE_REAL_ESTATE];

    /** Legacy types (no longer selectable) that still calculate Expired instead of Overdue. */
    public const RENEWAL_TYPES = ['Hosting', 'Domain', 'SSL', 'AMC'];

    public const STATUS_PENDING = 'Pending';

    public const STATUS_COMPLETED = 'Completed';

    /**
     * Types whose `status` is picked by hand from a fixed list (MANUAL_STATUSES)
     * instead of being calculated from the dates like Product/IT Service are.
     * "Completed" is a shared value, so completion (isCompleted(), the
     * Complete button, completed_at) works exactly the same either way.
     */
    public const MANUAL_STATUS_TYPES = [self::TYPE_TRAVEL, self::TYPE_REAL_ESTATE];

    public const MANUAL_STATUSES = ['Confirmed', 'Scheduled', 'Reminder', 'Processing', 'Due', 'Completed', 'Cancelled'];

    /** Values accepted by the `status` filter. */
    public const FILTER_STATUSES = [
        'Pending', 'Today', 'Upcoming', 'Completed', 'Overdue', 'Expired',
        'Confirmed', 'Scheduled', 'Reminder', 'Processing', 'Due', 'Cancelled',
    ];

    public const RANGES = ['today', 'tomorrow', 'next7', 'next30'];

    /**
     * Fields whose changes are worth recording in the Change History, and the
     * label they are recorded under. Internal/derived columns (id,
     * completed_at, timestamps) are deliberately left out.
     */
    public const TRACKED_FIELDS = [
        'reminder_type' => 'Reminder Type',
        'customer_name' => 'Customer / Company',
        'phone' => 'Phone',
        'product_name' => 'Product Name',
        'product_category_id' => 'Product Category',
        'quantity' => 'Quantity',
        'price' => 'Price',
        'website_link' => 'Website Link',
        'booking_name' => 'Booking / Tour Name',
        'property_name' => 'Property / Project Name',
        'location' => 'Location',
        'scheduled_date' => 'Scheduled / Due Date',
        'reminder_date' => 'Reminder Date',
        'assigned_to' => 'Assigned Person',
        'notes' => 'Notes',
        'status' => 'Status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'reminder_date' => 'date',
            'completed_at' => 'datetime',
            'quantity' => 'integer',
            'price' => 'decimal:2',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /** Change History: newest first. Nothing is recorded for creation, only later edits. */
    public function changes(): HasMany
    {
        return $this->hasMany(ReminderChange::class)->latest('created_at')->latest('id');
    }

    /** Today's date (Y-m-d) in the application timezone. */
    public static function today(): string
    {
        return now()->toDateString();
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Current state. For Travel / Real Estate this is simply whatever the
     * Status dropdown was last set to (MANUAL_STATUSES) — never calculated.
     * Every other type: Completed, Today, Upcoming, Overdue or Expired.
     */
    public function state(?string $today = null): string
    {
        if (in_array($this->reminder_type, self::MANUAL_STATUS_TYPES, true)) {
            return $this->status;
        }

        if ($this->isCompleted()) {
            return 'Completed';
        }

        $today ??= self::today();
        $reminder = $this->reminder_date->toDateString();
        $due = $this->scheduled_date->toDateString();

        if ($reminder === $today || $due === $today) {
            return 'Today';
        }

        if ($reminder > $today) {
            return 'Upcoming';
        }

        return $due < $today && in_array($this->reminder_type, self::RENEWAL_TYPES, true)
            ? 'Expired'
            : 'Overdue';
    }

    /** Days since the due date (or since the reminder date if the due date is still ahead). */
    public function daysOverdue(?string $today = null): int
    {
        $today ??= self::today();

        if (! in_array($this->state($today), ['Overdue', 'Expired'], true)) {
            return 0;
        }

        $since = $this->scheduled_date->toDateString() < $today ? $this->scheduled_date : $this->reminder_date;

        return (int) $since->diffInDays($today, true);
    }

    // ---------------------------------------------------------------------
    // Query scopes
    // ---------------------------------------------------------------------

    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('status', '!=', self::STATUS_COMPLETED);
    }

    /** Admin sees everything; a User only reminders assigned to their linked Assigned Person. */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        if (! $user->isAdmin()) {
            // Same "show nothing rather than everything" fallback as ReminderController::scoped()
            // if a User account is somehow missing its required Assigned Person.
            $query->where('assigned_to', $user->assigned_person_id ?? 0);
        }
    }

    #[Scope]
    protected function completed(Builder $query): void
    {
        $query->where('status', self::STATUS_COMPLETED);
    }

    #[Scope]
    protected function dueToday(Builder $query, string $today): void
    {
        $query->open()->where(fn (Builder $q) => $q
            ->where('reminder_date', $today)
            ->orWhere('scheduled_date', $today));
    }

    #[Scope]
    protected function upcoming(Builder $query, string $today): void
    {
        $query->open()
            ->where('reminder_date', '>', $today)
            ->where('scheduled_date', '!=', $today);
    }

    /** Overdue + Expired. */
    #[Scope]
    protected function pastDue(Builder $query, string $today): void
    {
        $query->open()
            ->where('reminder_date', '<', $today)
            ->where('scheduled_date', '!=', $today);
    }

    #[Scope]
    protected function expired(Builder $query, string $today): void
    {
        $query->pastDue($today)
            ->where('scheduled_date', '<', $today)
            ->whereIn('reminder_type', self::RENEWAL_TYPES);
    }

    /** Overdue only (past-due reminders that are not Expired). */
    #[Scope]
    protected function overdueOnly(Builder $query, string $today): void
    {
        $query->pastDue($today)->where(fn (Builder $q) => $q
            ->where('scheduled_date', '>', $today)
            ->orWhereNotIn('reminder_type', self::RENEWAL_TYPES));
    }

    /** Today + Upcoming, i.e. everything open that is not past due. */
    #[Scope]
    protected function notPastDue(Builder $query, string $today): void
    {
        $query->open()->where(fn (Builder $q) => $q
            ->where('reminder_date', '>=', $today)
            ->orWhere('scheduled_date', $today));
    }

    #[Scope]
    protected function inState(Builder $query, string $state, string $today): void
    {
        match ($state) {
            'Pending' => $query->open(),
            'Completed' => $query->completed(),
            'Today' => $query->dueToday($today),
            'Upcoming' => $query->upcoming($today),
            'Overdue' => $query->overdueOnly($today),
            'Expired' => $query->expired($today),
            // One of MANUAL_STATUSES (Confirmed, Scheduled, Reminder, Processing, Due, Cancelled):
            // a straight match against the stored column, not a calculated scope.
            default => $query->where('status', $state),
        };
    }

    /**
     * Apply the list filters shared by the index and summary endpoints.
     *
     * Recognised keys: view (today|upcoming|overdue|completed), search,
     * reminder_type, assigned_to (id, "unassigned", or omit for all), status,
     * range (today|tomorrow|next7|next30), date_from, date_to.
     *
     * Date filters apply to reminder_date. On the "upcoming" view, choosing a
     * date range widens the view from Upcoming to Today + Upcoming so that
     * "Today" and "Next 7 Days" return what people expect.
     */
    #[Scope]
    protected function filter(Builder $query, array $filters, ?string $today = null): void
    {
        $today ??= self::today();
        $todayDate = Carbon::parse($today);

        [$from, $to] = match ($filters['range'] ?? null) {
            'today' => [$today, $today],
            'tomorrow' => [$todayDate->copy()->addDay()->toDateString(), $todayDate->copy()->addDay()->toDateString()],
            'next7' => [$today, $todayDate->copy()->addDays(7)->toDateString()],
            'next30' => [$today, $todayDate->copy()->addDays(30)->toDateString()],
            default => [$filters['date_from'] ?? null, $filters['date_to'] ?? null],
        };

        if (filled($view = $filters['view'] ?? null)) {
            match ($view) {
                'today' => $query->dueToday($today),
                'upcoming' => ($from || $to) ? $query->notPastDue($today) : $query->upcoming($today),
                'overdue' => $query->pastDue($today),
                'completed' => $query->completed(),
                default => null,
            };
        }

        if (filled($status = $filters['status'] ?? null)) {
            $query->inState($status, $today);
        }

        if (filled($type = $filters['reminder_type'] ?? null)) {
            $query->where('reminder_type', $type);
        }

        $assignee = $filters['assigned_to'] ?? null;
        if ($assignee === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif (filled($assignee) && $assignee !== 'all') {
            $query->where('assigned_to', $assignee);
        }

        if ($from) {
            $query->where('reminder_date', '>=', $from);
        }
        if ($to) {
            $query->where('reminder_date', '<=', $to);
        }

        if (filled($search = trim($filters['search'] ?? ''))) {
            $like = '%'.addcslashes($search, '\\%_').'%';
            $digits = preg_replace('/\D+/', '', $search);

            $query->where(function (Builder $q) use ($like, $digits) {
                $q->where('customer_name', 'like', $like)
                    ->orWhere('product_name', 'like', $like)
                    ->orWhere('website_link', 'like', $like)
                    ->orWhere('booking_name', 'like', $like)
                    ->orWhere('property_name', 'like', $like)
                    ->orWhere('location', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('phone', 'like', $like);

                // Phones are stored as typed (spaces, +91...), so also match on digits only.
                if (strlen($digits) >= 3) {
                    $q->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '(', ''), ')', '') like ?",
                        ["%{$digits}%"],
                    );
                }
            });
        }
    }
}
