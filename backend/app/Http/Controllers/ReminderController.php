<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReminderFilterRequest;
use App\Http\Requests\ReminderRequest;
use App\Http\Resources\ReminderChangeResource;
use App\Http\Resources\ReminderResource;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ReminderChangeLogger;
use App\Services\ReminderExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReminderController extends Controller
{
    public function __construct(
        private readonly ReminderChangeLogger $changeLogger,
        private readonly ReminderExporter $exporter,
    ) {}

    /**
     * List reminders. All filtering, searching and paging happens in the database.
     * A User only ever sees reminders assigned to their linked Assigned Person,
     * regardless of what an `assigned_to` filter asks for; an Admin sees everything.
     */
    public function index(ReminderFilterRequest $request): AnonymousResourceCollection
    {
        $filters = $this->scoped($request->validated(), $request->user());

        $query = Reminder::query()->with(['assignee:id,name', 'category:id,name'])->filter($filters);

        // Most urgent first; the id is a tie-break so paging is stable.
        match ($filters['view'] ?? null) {
            'overdue' => $query->orderBy('scheduled_date')->orderBy('reminder_date')->orderBy('id'),
            'today', 'upcoming' => $query->orderBy('reminder_date')->orderBy('scheduled_date')->orderBy('id'),
            'completed' => $query->orderByDesc('completed_at')->orderByDesc('id'),
            default => $query->orderByDesc('id'),
        };

        return ReminderResource::collection($query->paginate($filters['per_page'] ?? 15)->withQueryString());
    }

    /**
     * "Export Excel": every reminder matching the currently applied filters
     * (the same ones the table uses), or all reminders if none are applied —
     * scoped to a User's own Assigned Person the same way index() is. Not
     * paginated — the whole matching set is exported, not just the page shown.
     */
    public function export(ReminderFilterRequest $request): StreamedResponse
    {
        $filters = collect($request->validated())->except(['per_page', 'page'])->all();
        $filters = $this->scoped($filters, $request->user());

        return $this->exporter->download($filters);
    }

    /**
     * Counts for the dashboard. They follow the search, type and staff filters
     * but ignore the view, status and date filters (those pick a count), and
     * are scoped to a User's own Assigned Person the same way index() is.
     */
    public function summary(ReminderFilterRequest $request): JsonResponse
    {
        $filters = collect($request->validated())->only(['search', 'reminder_type', 'assigned_to'])->all();
        $filters = $this->scoped($filters, $request->user());
        $today = Reminder::today();

        $count = fn (string $scope) => Reminder::query()->filter($filters, $today)->{$scope}($today)->count();

        return response()->json([
            'today' => $count('dueToday'),
            'upcoming' => $count('upcoming'),
            'overdue' => $count('pastDue'),
            'completed' => Reminder::query()->filter($filters, $today)->completed()->count(),
            'date' => $today,
        ]);
    }

    /**
     * Creating a reminder never writes Change History: the values entered
     * here are the starting point, not a change from anything. A User's new
     * reminder is always assigned to their own linked Assigned Person.
     */
    public function store(ReminderRequest $request): JsonResponse
    {
        $reminder = Reminder::create($this->attributes($request));

        return (new ReminderResource($reminder->load(['assignee:id,name', 'category:id,name'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Reminder $reminder): ReminderResource
    {
        $this->authorize('view', $reminder);

        return new ReminderResource($reminder->load(['assignee:id,name', 'category:id,name']));
    }

    /** Change History, newest first. */
    public function history(Reminder $reminder): AnonymousResourceCollection
    {
        $this->authorize('view', $reminder);

        return ReminderChangeResource::collection($reminder->changes()->with('changedBy:id,name')->get());
    }

    /** A User can never move a reminder to someone else's Assigned Person. */
    public function update(ReminderRequest $request, Reminder $reminder): ReminderResource
    {
        $this->authorize('update', $reminder);

        $before = $reminder->getOriginal();

        $reminder->update($this->attributes($request, $reminder));
        $this->changeLogger->record($reminder, $before, $request->user());

        return new ReminderResource($reminder->load(['assignee:id,name', 'category:id,name']));
    }

    public function complete(Request $request, Reminder $reminder): ReminderResource
    {
        $this->authorize('update', $reminder);

        $before = $reminder->getOriginal();

        $reminder->forceFill([
            'status' => Reminder::STATUS_COMPLETED,
            'completed_at' => $reminder->completed_at ?? now(),
        ])->save();
        $this->changeLogger->record($reminder, $before, $request->user());

        return new ReminderResource($reminder->load(['assignee:id,name', 'category:id,name']));
    }

    public function destroy(Reminder $reminder): Response
    {
        $this->authorize('delete', $reminder);

        $reminder->delete();

        return response()->noContent();
    }

    /**
     * Validated input, with completed_at kept in step with the status. A
     * User can never set assigned_to to anyone but their own linked
     * Assigned Person — the field is forced here regardless of what was
     * submitted, not just hidden in the UI.
     *
     * @return array<string, mixed>
     */
    private function attributes(ReminderRequest $request, ?Reminder $existing = null): array
    {
        $data = $request->validated();

        if (isset($data['status'])) {
            $data['completed_at'] = $data['status'] === Reminder::STATUS_COMPLETED
                ? ($existing?->completed_at ?? now())
                : null;
        }

        if (! $request->user()->isAdmin()) {
            $data['assigned_to'] = $request->user()->assigned_person_id;
        }

        return $data;
    }

    /**
     * Forces the `assigned_to` filter to a User's own linked Assigned Person,
     * regardless of what was asked for. Left untouched for an Admin.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function scoped(array $filters, User $user): array
    {
        if (! $user->isAdmin()) {
            // A User is always required to have exactly one Assigned Person (see
            // UserRequest). If that is somehow missing anyway, show nothing rather
            // than let the filter become a no-op and expose every reminder.
            $filters['assigned_to'] = $user->assigned_person_id !== null ? (string) $user->assigned_person_id : '0';
        }

        return $filters;
    }
}
