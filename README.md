# LIZY REMINDER

Internal reminder management for Lizyweb: **add reminder → assign person → reminder date → track → complete.**

| Part     | Stack                                                  |
| -------- | ------------------------------------------------------ |
| Frontend | React 19 + Vite + React Router (`frontend/`)           |
| Backend  | Laravel 13 REST API + Sanctum token auth (`backend/`)  |
| Database | MySQL 8                                                |

The React app only talks to the Laravel API (`/api/...`). Filtering, searching, date logic and paging all happen in MySQL queries.

There are four reminder types, each with its own fields:

- **Product** — Product Name, Product Category (from the `product_categories` table), Phone, Quantity, Price (optional).
- **IT Service** — Website Link (clickable, opens in a new tab), Phone.
- **Travel / Family Tour Booking** — Booking / Tour Name, Travel Date (reuses the same underlying date field as Product/IT Service's "Scheduled / Due Date", just relabelled).
- **Real Estate Marketing** — Property / Project Name, Location, Marketing Follow-up Date (same underlying date field, relabelled again).

All four also have Customer/Company, Phone, Assigned Person (or "Unassigned"), Reminder Date and Notes. The Add/Edit form shows only the fields for the chosen type.

**Status.** Product and IT Service are unchanged: only Pending/Completed is stored, and Today/Upcoming/Overdue/Expired are calculated from the dates (see "How reminder status works" below). Travel / Family Tour Booking and Real Estate Marketing work differently — their Status is a dropdown picked by hand, not calculated: **Confirmed, Scheduled, Reminder, Processing, Due, Completed, Cancelled** (`Reminder::MANUAL_STATUS_TYPES` / `MANUAL_STATUSES`). It's required on their Add/Edit form (in place of the Completed checkbox the other types use), it's exactly what's shown as that reminder's status everywhere in the UI, and "Completed" is the one value shared with the other types — so the Complete button, `is_completed` and `completed_at` all work the same way regardless of type. The Today/Upcoming/Overdue dashboard tabs still bucket every type by date exactly as before (a Travel reminder due today still appears under Today's Work); it's only the *status badge shown on it* that differs by type. One nuance worth knowing: because only the literal "Completed" status is excluded from the "open" tabs, a "Cancelled" Travel/Real Estate reminder still shows up under Today's Work/Upcoming/Overdue for as long as its date logic puts it there — there's no separate "closed" bucket for Cancelled.

The reminder table, filters, search, Excel export and Change History all adapt to whichever type(s) are in view: filtered to a single type, columns and field labels switch to match it (e.g. "Booking / Tour Name" + "Travel Date" for Travel); with no type filter, a merged "Details" column shows whichever field applies to each row and the date column keeps a generic label, the same way it already did for Product vs. IT Service.

## Run it

Requirements: PHP 8.3+ (with `pdo_mysql`, `mbstring`, `openssl`), Composer, Node 20+, MySQL 8.

### 1. Database

Create the databases (the second one is only for the test suite):

```sql
CREATE DATABASE lizy_reminder CHARACTER SET utf8mb4;
CREATE DATABASE lizy_reminder_test CHARACTER SET utf8mb4;
```

### 2. Backend

```powershell
cd backend
composer install
# edit .env: DB_USERNAME / DB_PASSWORD (and DB_HOST / DB_PORT if not 127.0.0.1:3306)
php artisan migrate --seed
php artisan serve          # http://127.0.0.1:8000
```

`backend/.env` already has `DB_CONNECTION=mysql`, `DB_DATABASE=lizy_reminder`, `DB_USERNAME=root`. Fill in `DB_PASSWORD`. (`APP_KEY` is already generated.)

### 3. Frontend

```powershell
cd frontend
npm install
npm run dev                # http://localhost:5173
```

In development Vite proxies `/api` to `http://127.0.0.1:8000`, so nothing else needs configuring. For a production build (`npm run build`, output in `frontend/dist`) served from another origin, set `VITE_API_URL` (see `frontend/.env.example`) and put that origin in `FRONTEND_URL` in `backend/.env` (CORS).

### Log in

The shared team login (**Admin**): **omsreminder@gmail.com** / `password`.

Staff (assignable, and can also log in) are rows in the `users` table with `is_staff = true`, seeded by `DatabaseSeeder`: Shreen Rozan, Asma, Aafrin, Suliha, Sahana, Sathika, Gokul, Rakesh, Anas, Ashif, Safron, Rashik, Rahmath, Ravikumar, Rafeek, Vazeem — all at `<firstword-lowercase>@lizyweb.in` (e.g. `sahana@lizyweb.in`), password `Lizy@2026`. Each one is a **User** account linked to themselves as their own Assigned Person (see Roles below).

The seed password comes from `SEED_STAFF_PASSWORD` in `.env`. Change it before real use. Add or remove someone from the **assignable** list by editing their `is_staff` flag in the `users` table (the dropdowns read `is_staff = true` rows); this doesn't affect their ability to log in. Manikandan, Pavithran and Yasmin (from an earlier staff list) are kept in the table with `is_staff = false` and role Admin: they can still log in and any reminder already assigned to them still shows their name, but they no longer appear in the "Assigned Person" pickers.

Product categories (Pump, Motor, Electrical, Hardware, Accessories, Other) are rows in `product_categories`; add more there without any schema change.

### Tests

```powershell
cd backend
php artisan test           # uses the lizy_reminder_test database
```

94 tests cover login, all four reminder types' conditional validation (including the Travel/Real Estate manual Status dropdown), product categories, the staff list, CRUD, the date logic (with the date moved to 21, 23 and 25 Sept 2026), completion, search, combined filters, paging, that a reminder saved under an older/removed type still works, the Change History rules, the Excel export, the roles/Manage Users rules (including the password-change rules below), and the notification bell below.

## Roles

Two roles, on the `users` table (`role`: `admin` / `user`):

- **Admin** — sees and manages every reminder and every user account.
- **User** — scoped to reminders assigned to their linked Assigned Person (`assigned_person_id` on their `users` row, another row with `is_staff = true`): the reminder list, dashboard counts, Excel export and the Assigned Person filter are all scoped to it, unassigned reminders are invisible to them, and opening someone else's reminder directly by URL returns 403. Their Add/Edit Reminder form shows the Assigned Person field locked to their own name — they can never move a reminder to anyone else, enforced on the backend regardless of what the UI sends. This link is set directly in the `users` table (the seeded staff are self-linked by `DatabaseSeeder`) — **Manage Users** no longer sets it (see below), so a brand-new User account created there has no linked person until one is set that way, and sees no reminders until it is.

**Manage Users** (Admin only, hidden from the sidebar and blocked by both the frontend route and the API for a User) is a full CRUD screen — Name, Email, Role, Actions — at `/users`. It's the same form for both roles: neither Admin nor User needs anything beyond Name, Email, Role and a password to be created or edited here — there's no Assigned Person field on this screen for either role (that's a separate concept from the reminder-assignment system above; it isn't touched by this form and keeps whatever value it already has). An account can't delete itself, and can't be deleted while still linked as someone else's Assigned Person.

**Passwords**: creating an account requires New Password + Confirm Password (must match, min 6 characters, hashed via the `password` => `hashed` cast — i.e. `Hash::make()`). Editing an existing account can leave both blank to keep the current password; if New Password is filled in, Confirm Password becomes required and must match — that's the only confirmation needed, for anyone's password including the signed-in Admin's own via self-edit.

## How reminder status works

This section is about Product and IT Service (and any older legacy type). Travel / Family Tour Booking and Real Estate Marketing don't calculate a status at all — see "Status" above.

Only **Pending** or **Completed** is stored (`reminders.status`). Everything else is calculated from the dates and *today's date* (application timezone `Asia/Kolkata`) every time, in SQL (`App\Models\Reminder` scopes) and in PHP (`Reminder::state()`), which follow identical rules:

| State         | Rule (not completed)                                                                                     |
| ------------- | -------------------------------------------------------------------------------------------------------- |
| **Today**     | reminder date **or** scheduled/due date is today                                                          |
| **Upcoming**  | reminder date is after today                                                                              |
| **Overdue**   | reminder date has passed (and the due date isn't today)                                                   |
| **Expired**   | as Overdue, but only reachable by a reminder saved under an older, no-longer-selectable renewal type (Hosting/Domain/SSL/AMC) whose due date has also passed — Product and IT Service reminders never become Expired |
| **Completed** | status is Completed. It is never Overdue.                                                                 |

*Days Overdue* counts from the due date (or from the reminder date if the due date is still ahead). The status filter's **Pending** means "not completed". The reminder date must be on or before the due date.

With today = 21/09/2026 the seeded 23/09 and 29/09 reminders are Upcoming; on 23/09 the two 23/09 ones become Today's Work; after that they turn Overdue until completed.

## Change History

Shown at the bottom of the View modal. Rules:

- Creating a reminder writes nothing — the values entered are the starting point, not a change.
- Every Edit/Update (and Complete) that actually changes a field writes one entry per changed field: field name, previous value, new value, who changed it, and when. Resubmitting a field with its existing value writes nothing.
- Newest first. Kept permanently (`reminder_changes` table, deleted only if the reminder itself is deleted).
- `assigned_to` and `product_category_id` are recorded as the resolved name at the time of the change (e.g. "Gokul", "Pump"), not the raw id, so history stays readable even if that staff member or category is later renamed or removed.
- Applies the same way to Product and IT Service reminders — whichever fields exist on that reminder.

## Notification bell

The bell in the header (next to the account name) shows a count of **unread** alerts for reminders whose **Reminder Date is today** — nothing else. Overdue, Upcoming, Completed and anything not due today are never alerts (they're still visible everywhere else in the app, just not a notification). Scoped the same way as everywhere else: Admin sees today's reminders for every staff member, a User only reminders assigned to their linked Assigned Person.

Clicking the bell opens a small panel — customer, product/website and reminder date for each — capped at 15 rows for readability, with the true unread count shown even when there are more. Opening a notification marks it **read** (stored in `notification_reads`, per user/reminder/date — it never touches the reminder itself) and drops it out of the count immediately, then takes you to All Reminders with that reminder's View page open. Read status persists across refresh, logout and login; if a read reminder's Reminder Date is later changed and eventually becomes "today" again, it correctly shows as unread again. The badge refreshes when the panel is opened and polls the database every 60 seconds.

## Export Excel

The **Export Excel** button on the All Reminders page downloads an `.xlsx` file of every reminder matching the filters currently applied there (or every reminder, if none are applied) — not just the current page. Requires the PHP `zip` and `gd` extensions (enabled in `C:\php\php.ini` for this setup; `phpoffice/phpspreadsheet` needs them to write `.xlsx`).

## API

All routes except `/login` need `Authorization: Bearer <token>`.

| Method | Path                            | Purpose                                   |
| ------ | ------------------------------- | ------------------------------------------ |
| POST   | `/api/login`                    | `{email, password}` → `{token, user}`     |
| POST   | `/api/logout`, GET `/api/me`    | end session / current user                |
| GET    | `/api/users`                    | assignable staff (`is_staff = true`)      |
| GET    | `/api/product-categories`       | product categories for the Product form/table |
| GET    | `/api/notifications`            | unread today-only notification count + list (scoped for a User) |
| POST   | `/api/notifications/{id}/read`  | mark that reminder's notification read (403 for a User if not theirs) |
| GET    | `/api/reminders`                | list (filters below, paginated; scoped for a User) |
| GET    | `/api/reminders/export`         | download an `.xlsx` of every reminder matching the filters below (unpaginated; scoped for a User) |
| GET    | `/api/reminders/summary`        | dashboard counts: today / upcoming / overdue / completed (scoped for a User) |
| POST   | `/api/reminders`                | create (a User's is always assigned to themselves) |
| GET    | `/api/reminders/{id}`           | show (403 for a User if not theirs)       |
| PUT    | `/api/reminders/{id}`           | update (`status`: Pending / Completed; 403 for a User if not theirs, and their `assigned_to` can't be changed) |
| POST   | `/api/reminders/{id}/complete`  | mark completed (403 for a User if not theirs) |
| GET    | `/api/reminders/{id}/history`   | Change History, newest first (403 for a User if not theirs) |
| DELETE | `/api/reminders/{id}`           | delete (403 for a User if not theirs)     |
| GET/POST/PUT/DELETE | `/api/manage-users`, `/api/manage-users/{id}` | Manage Users — Admin only (403 for a User); fields are `name`, `email`, `role`, `password`/`password_confirmation`; `password`/`password_confirmation` required on create, optional on update (required together and must match if `password` is set) |

List filters (all combine with AND): `view` (`today`, `upcoming`, `overdue`, `completed`), `search` (customer, phone, product name, website link, booking/tour name, property/project name, location, notes; phone matches ignore spaces and +), `reminder_type` (`Product`, `IT Service`, `Travel / Family Tour Booking` or `Real Estate Marketing`), `assigned_to` (staff id, `unassigned`, or omit for all — ignored for a User, always forced to their own linked person), `status` (the calculated values, or — for Travel/Real Estate — any of `Reminder::MANUAL_STATUSES` directly), `range` (`today`, `tomorrow`, `next7`, `next30`), `date_from`, `date_to` (both on the reminder date), `per_page`, `page`. On the `upcoming` view a date range widens it to Today + Upcoming so "Today" and "Next 7 Days" work.

`reminder_type` on create/update only accepts one of the four current types — except when updating a reminder that already carries an older type (e.g. from before this list existed), which can keep that type without being forced onto one of them. `assigned_to` is optional for an Admin (send `null` for "Unassigned"); a User's reminders are always assigned to their own linked person regardless of what is sent. Required fields depend on the type: Product needs `product_name`, `product_category_id`, `quantity`; IT Service needs `website_link` (a scheme is added automatically if you send e.g. `example.com`); Travel / Family Tour Booking needs `booking_name`; Real Estate Marketing needs `property_name` and `location`. The latter two also require `status` (one of `Reminder::MANUAL_STATUSES`) on every save, rather than it defaulting to Pending like Product/IT Service.

## Notes

- The seeded reminders' phone numbers are stored exactly as supplied. WhatsApp links add `91` to bare 10-digit numbers; other numbers are used as stored.
- Customer left blank is saved as "Not Provided".
- The reminder table's columns adapt to the active Reminder Type filter: filtered to Product it shows Product/Category/Qty, filtered to IT Service it shows Website Link, and with no type filter it shows a combined column (product name or the site link, whichever applies to that row).
- Set `APP_DEBUG=false` in `backend/.env` for production.
