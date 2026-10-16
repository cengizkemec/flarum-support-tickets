# Changelog

## 1.1.1 — 2026-10-16

Verified end-to-end on a live Flarum 2.0.1 installation (PHP 8.4, MariaDB 11).

### Fixed (admin panel)

- **Root cause of both admin bugs found and fixed.** The admin JavaScript bundle never
  registered the extension's models in the store. Because of this:

  - `app.store.find('ticket-categories')` returned `null` entries, and
  - `app.store.createRecord('ticket-categories')` threw
    `Pushing object of type 'ticket-categories' not allowed, as type not yet registered in the store.`

  The forum bundle already did this, the admin bundle did not. `js/src/admin/extend.ts` now
  registers `tickets`, `ticket-categories` and `ticket-replies` through `Extend.Store()`.
  This single fix restores both the settings page and category saving.

- **Settings page rewritten.** It now renders inside the standard
  `.ExtensionPage-settings > .container` layout and includes a real category manager
  (create, edit, toggle active, delete) with loading, empty and error states — instead of the
  old single-button page that relied on `window.prompt()` and hardcoded English strings.

- Category deletion now uses a translated confirmation dialog (`extractText`) instead of a
  hardcoded English `confirm()` message.

- Added `less/admin.less` (self-contained, CSS custom properties, dark-mode aware) and
  registered it with `(new Extend\Frontend('admin'))->css(...)`.

### Added

- Full English **and** Turkish admin locale keys under `admin.categories.*`
  (labels, help texts, success/error messages, confirm dialog).

### Fixed (tooling / locales)

- `resources/locale/{en,tr}.yml` failed to compile because `auto_close_days_help` contained a
  bare colon (`Allowed values: 1-365.`). The value is now quoted, so the locale files parse again.

### Changed

- The extension's admin page no longer contains any hardcoded user-facing English text.

## 1.1.0 — 2026-10-16

A complete redesign of the front end, a full pass over the Turkish/English translations,
and the fix for the deep-link bug. Tested live on warthunder.tr.

### New design

- **Support Center** — colored hero header, description and a prominent "New Ticket" button.
- **Summary cards** — total / open / closed ticket counts.
- **Filter tabs** — All / Open / Closed, with counters.
- **Ticket cards** — status color bar, priority and status badges, category, date, reply count
  and assigned staff, with a hover animation.
- **Chat-style ticket view** — avatar, message cards and highlighted staff replies in a timeline.
- **Staff action panel** — status menu, assignee menu (fed by the real staff list),
  "Mark as Resolved" and "Close" / "Reopen" buttons.
- Empty state screen, loading indicator and dark-mode support.

### Fixed

- **Deep-link bug fixed:** `/support/{id}` is now registered on the server as well. Previously,
  refreshing or sharing a ticket link returned **404**.
- Critical frontend error: the `Component` path was corrected to `flarum/common/Component`
  for Flarum 2.0, so the extension now loads and its routes are registered.
- **Every UI string is now translatable:** priority labels and options
  (Low / Medium / High / Critical), status labels, upload texts, assignment fields, buttons and
  error messages are no longer hardcoded English.
- Dates and times are now formatted according to the forum's locale (e.g. `tr`).

### Added

- `supportStaff` forum attribute: exposes the staff members (administrators plus groups granted
  the `support.viewAll` permission) to the front end, feeding the "Assigned to" menu.

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
