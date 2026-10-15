# Changelog

## 1.0.8 — 2026-10-15

Verified end-to-end against a live Flarum 2.0.1 installation (PHP 8.4, MariaDB 11).

### Fixed (fatal errors)
- `TicketResource::updating()` called the Laravel `now()` helper, which Flarum does not autoload — closing a ticket failed with HTTP 500. Now uses `Carbon::now()`.
- `DownloadAttachmentController` read the route parameter from request *attributes*, but Flarum's `Extend\Routes` merges route parameters into the *query params*, so downloads always failed. Now reads `Arr::get($request->getQueryParams(), 'filename')`.
- `DownloadAttachmentController` built error responses with `new Response(null, …)`; Diactoros rejects a `null` body, so every 400/403/404 path threw a 500. Now uses `EmptyResponse`.
- Notification e-mails crashed with `Call to undefined function url()`. The flat Blade mail views were replaced with Flarum 2's `x-mail::plain.notification` / `x-mail::html.notification` components.

### Fixed (JSON:API)
- `TicketResource` did not declare `sorts()`, so `GET /api/tickets?sort=-createdAt` returned 400. Added `createdAt` / `updatedAt` sort columns.
- The `category` relationship was not writable, so it could not be set when creating a ticket.
- `TicketReplyResource`'s `ticket` relationship was not `writableOnCreate()`, so a reply could not be linked to its ticket.
- `Ticket`, `TicketReply` and `TicketCategory` relied on the removed Eloquent `$dates` property and never wrote timestamps. Replaced with `$timestamps = true` and `$casts`.
- The staff recipient query referenced a non-existent `users.is_admin` column (fatal SQL error). Admins are now resolved via group 1 plus the `support.viewAll` permission.

### Fixed (notifications)
- Neither blueprint implemented `AlertableInterface`, so the Alert driver silently skipped them and **no in-forum notifications were ever created**. Both blueprints now implement it.
- When a requester replied to a ticket that was not assigned to anyone, no staff were notified, so tickets could stall. Requester replies now notify all staff.
- Staff are no longer notified about tickets they created themselves.

### Fixed (frontend)
- `TicketPage` used `app.session.user.can(…)`, which does not exist; the staff UI is now driven by the `canSupportViewAll` / `canSupportManage` forum attributes (exposed via a new `ForumResource` API field).
- Assigning a ticket used `app.store.getById()`, which returns `undefined` for users that are not loaded yet; now uses `await app.store.find('users', id)`.
- The create-ticket category list now hides inactive categories and surfaces errors instead of failing silently.

### Other
- Added `resources/views/emails/{plain,html}/{newTicket,ticketReply}.blade.php` and `email.*.body` locale keys (en/tr).
- Removed a duplicated block in this changelog.

## 1.0.7 — 2026-10-13

### Fixed / Verified
- Confirmed working on a real Flarum 2.0.0 installation (PHP 8.3, SQLite).
- `extension:enable` succeeds without fatal errors.
- Migrations create `ticket_categories`, `tickets`, `ticket_replies` tables.
- `support-tickets:auto-close` command runs and returns `0`.
- Added `$guarded = []` on models to avoid mass-assignment issues.

## 1.0.6 — 2026-10-12

### Fixed
- Removed invalid third argument from `serializeToForum()`. Passing `14` caused `ReflectionException: Class "14" does not exist` because the parameter is a callback, not a default value.
- Default for `auto_close_days` remains `14` via `->default()`.

## 1.0.5 — 2026-10-12

### Fixed
- Renamed `getEmailView()` to `getEmailViews()` in notification blueprints to match Flarum 2.0 `MailableInterface`.
- Prevents fatal error: "Class NewTicketBlueprint contains 1 abstract method ... (MailableInterface::getEmailViews)".

## 1.0.4 — 2026-10-12

### Fixed
- Notification blueprints now declare return types required by Flarum 2.0 `BlueprintInterface`:
  - `getFromUser(): ?User`
  - `getSubject(): ?AbstractModel`
  - `getData(): mixed`
- Prevents fatal error on extension enable when notification types are registered.

## 1.0.3 — 2026-10-12

### Fixed
- Console command `AutoCloseTicketsCommand::fire()` now returns `int` to be compatible with `Flarum\Console\AbstractCommand::fire(): int`.
- Early exit and success paths both return `0`.

## 1.0.2 — 2026-10-12

### Security
- Attachments are now stored in private `storage/support-tickets/` instead of public assets.
- Added authenticated download endpoint that verifies ticket view permission.
- Strengthened upload validation (MIME content check, dangerous filename rejection, no path traversal).
- Enforced server-side `user_id` on ticket creation (already present, reinforced).
- Restricted status, priority and assignee updates to staff with appropriate permissions.
- Reply permission now consistent with TicketPolicy (owners on open tickets; staff with reply/viewAll/manage).
- Closed/resolved tickets reject replies from non-staff.
- Invalid status/priority values are rejected.

### Features
- Implemented `support-tickets:auto-close` console command and daily schedule.
- Improved notification recipient selection and error isolation (failures no longer break ticket creation).

### Frontend
- Better error messages for 403/404/422.
- Prevented double-submit on reply form.
- Status updates revert on failure.
- Attachment links now use authenticated download URL.

### Other
- Updated README with installation, permissions, cron, and security behavior.
- No data-destroying migrations.
