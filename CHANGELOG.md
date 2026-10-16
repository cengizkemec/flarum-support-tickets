# Changelog

## 1.1.8 — 2026-10-16

This release completes the departments feature begun in the 1.1.4–1.1.6 commits, brings
Flarum's composer to the ticket forms, and hardens the extension's input handling.

### Added (departments)

- **Departments manager in the admin panel.** Create, rename (slug), describe, activate and
  delete departments next to the category manager. A department that is deleted has its
  categories unlinked first, so nothing dangles.
- **Member management.** Every department row opens a modal where the administrator searches
  users (debounced, avatar + display name) and adds or removes members. Deleted accounts are
  dropped from the list on open, and the member list is stored server-side as ids that are
  filtered against the users table.
- **Categories link to a department.** The category form gained a department select and the
  category table a per-row select that saves on change; "No department" keeps the category on
  the general support team.
- **Everything follows the department.** A new ticket in a category linked to a department is
  auto-assigned to the department member with the fewest open tickets, and new-ticket /
  requester-reply notifications go only to that department's members (plus the assignee). When
  a department is empty or disabled, routing falls back to the whole support team.
- **Forum visibility.** Tickets show their department as a chip on the list and ticket page,
  and the new-ticket modal prints which department answers the selected category. Member ids
  are only serialised for administrators.

### Added (rich text editor)

- **The ticket forms now use Flarum's composer editor.** Message and reply fields render the
  exact `TextEditor` the composer uses, so whatever formatting extensions the forum has
  (markdown, BBCode, emoji, ...) contribute their toolbar buttons, keyboard shortcuts
  (Ctrl+B/I, Ctrl+Enter to submit) and emoji autocomplete automatically, on every forum.
  Paste-to-attach keeps working because the paste handler is wired to the editor's textarea.
- **Messages render formatted.** Tickets and replies expose a `messageHtml` attribute produced
  by the same server-side s9e formatter posts use, and the ticket view renders it with
  Flarum's `Post-body` styles (lists, quotes, code, links). A formatter failure falls back to
  escaped, line-broken plain text.
- Ticket list excerpts are derived from the formatted output as plain text.

### Security

- Message length is capped at 15,000 characters (`tickets.message`, `ticket_replies.message`)
  so a crafted request cannot exceed the TEXT column or force the formatter to digest an
  enormous body. Category name/slug/description, department slug, department member lists
  (500) and form-field choice lists (200) are bounded the same way.
- `ticket_categories.department_id` is validated with `exists:support_departments,id`.
- A ticket's `attachment` can only ever hold a server-generated filename; crafted values are
  refused on update even for staff, and the stored value is kept.
- The ticket-list excerpt parser uses `DOMParser` instead of `innerHTML`, so formatted content
  can never execute scripts or load resources while being converted to text.
- Re-verified: attachment uploads are MIME-sniffed with `finfo`, stored under random names
  with `0640` permissions; downloads enforce ticket authorization, `nosniff`, a sandboxing CSP
  and force raster images only inline; formatted message HTML is produced exclusively by the
  core s9e formatter, the same trust chain as posts.

## 1.1.2 — 2026-10-16

Verified end-to-end on a live Flarum 2.0.1 installation (PHP 8.4, MariaDB), including a
33-check automated security regression suite (**33 PASS / 0 FAIL**).

### Fixed (notifications)

- **Ticket notifications were rendered as blank rows.** The server created `newTicket` and
  `ticketReply` notifications, but no forum-side notification component was registered for
  those types. Flarum renders `null` for an unknown type, so the row had no icon, no message
  and — critically — **no link**, which is why tapping a notification on mobile navigated
  nowhere useful and clicking one on desktop did nothing.
  `js/src/forum/components/TicketNotifications.tsx` now provides `NewTicketNotification`
  (headset icon) and `TicketReplyNotification` (reply icon), registered with
  `new Extend.Notification()`. Each row shows the sender, the action text, a relative date
  and an excerpt of the ticket subject.
- Both new ticket types can now be enabled/disabled per user in the notification preferences
  grid (`Admin → Settings` and the user's own notification settings).
- Notification payloads now carry the ticket id (`ticketId`, plus `replyId` for replies), so
  the link keeps working even if the subject model has not been pushed into the store yet.

### Fixed (guests and navigation)

- **The "Log in" button on the guest `/support` page did nothing.** `LogInModal` and
  `SignUpModal` are chunked core modules; they were imported statically, so the module was
  not in the bundle yet and `app.modal.show(undefined)` failed silently. Both modals are now
  loaded with a lazy `import()`, which is the mechanism Flarum's chunk loader supports.
  The guest page now reliably opens the login and registration modals.
- **"Destek" (Support) is now always visible in the left sidebar** on the discussion index,
  including for guests, so the support centre is discoverable without logging in.
- **No way back from a ticket.** A "Back to Support" link now sits above the ticket subject on
  every ticket page. Closed tickets additionally show a note with a "Back to Support" button
  in place of the reply form (staff keep the reply form so they can reopen).
- **Wrong browser-tab title.** `/support` and ticket pages never set the document title, so
  the tab kept whatever the previous page had (e.g. "All Discussions"). The support centre now
  sets its own title and each ticket page uses its subject, e.g.
  `Ses ve mikrofon sorunu - Forum Name`.

### Added (custom ticket form fields)

- **HESK-style custom form fields, managed from the admin panel.** The settings page gained a
  "Ticket form fields" manager (create, edit, toggle active, delete) with the same loading,
  empty and error states as the category manager.
  - Types: `text`, `textarea`, `number`, `email`, `url`, `date`, `select`, `radio`, `checkbox`.
  - Fields can be **global** (shown for every ticket) or **scoped to one category**.
  - Per-field `required` and `active` flags plus a `position` for ordering.
  - `select`, `radio` and `checkbox` take a choices list entered one per line in the admin
    (stored as JSON in `ticket_form_fields.options`).
- **The "New Ticket" modal renders the configured fields.** Radio groups render as a vertical
  `role="radiogroup"`, checkboxes as a multi-select group, and `select`/`textarea`/`number`
  inputs use the standard Flarum form controls. Field values ride along on ticket creation.
- **Server-side validation.** `Support\TicketCustomFields::sanitize()` only keeps values whose
  field is active *and* applicable to the ticket's category, coerces each value to the shape
  its type expects (numeric coercion, `FILTER_VALIDATE_EMAIL` / `_URL`, `YYYY-MM-DD` date
  check, membership in the choices list for `select`/`radio`), and truncates free text to
  5 000 characters — so crafted `POST`s cannot write garbage into `tickets.custom_fields`.
- **Answers are shown inside the ticket** as label/value rows
  (`Support\TicketCustomFields::present()`). A field that is deleted later keeps its stored
  value visible under a generic label, so no ticket history is lost.
- New migration `2026_10_18_000001_create_ticket_form_fields_table.php` plus
  `2026_10_18_000002_add_custom_fields_to_tickets_table.php` (JSON column on `tickets`).

### Added (support team, URLs, PWA)

- **Support team group setting.** The admin panel now lets you pick the group that is "the
  support team". `Support\TicketStaff` resolves the team from that group (falling back to
  administrators + `support.viewAll` holders when unset) and **hands each brand-new,
  unassigned ticket to a team member automatically** (`pickAssignee()` in
  `TicketResource::creating()`). The team also feeds notifications and the "Assigned to" menu.
- **Readable ticket URLs.** Ticket links now carry a slug:
  `/support/18/ses-ve-mikrofon-sorunu`. The route is registered as
  `/support/{id:\d+}[/{slug}]`, so the bare `/support/18` form keeps working. The slug is
  generated by `Support\TicketSlug` (server) and `js/src/common/utils/slug.ts` (client) with
  the same Turkish-aware transliteration (ç→c, ğ→g, ı→i, ö→o, ş→s, ü→u) and NFC normalisation,
  so both sides always agree. `js/src/forum/utils/ticketUrl.ts` builds the canonical links.
- **FoF PWA integration.** `PwaServiceProvider` binds a `TicketNotificationBuilder` so push
  notifications produced by `fof/pwa` link straight to the ticket. The binding is guarded by
  `class_exists`, so the extension still works on forums without FoF PWA.

### Security

- **`TicketResource::updating()` now restores every protected field** for actors without
  `support.viewAll` / `support.manage`. Previously only status, priority and assignee were
  restored, so a non-staff owner could still overwrite the subject, message, attachment,
  category and the owning user id through a crafted `PATCH`. Verified by the automated suite.
- **Attachment downloads are hardened.**
  - Raster images (JPEG/PNG/GIF/WebP) are served `inline` so screenshots display in the
    ticket view; every other type is forced to download.
  - `Content-Security-Policy: default-src 'none'; sandbox` is added, so a file that is ever
    navigated to directly is neutralised by the browser.
  - `X-Content-Type-Options: nosniff` and `Cache-Control: private, no-cache` remain in place.
- Upload filename handling re-verified: the stored name is always
  `Str::random(40) + '.' + <extension derived from the verified MIME>`, and the original name
  is passed through `basename()` for display only. A crafted `../../name.png` upload is
  therefore inert (it cannot escape the storage directory) — the security suite asserts this
  explicitly.

### Added

- **`support-tickets:purge-attachments` console command.** Removes attachments that are no
  longer referenced by any ticket or reply (uploads that were never submitted, files whose
  ticket was deleted). Files younger than 24 hours are skipped so an in-flight upload is never
  deleted. Registered with Flarum's scheduler and runs daily.
- `js/src/forum/utils/attachments.ts` — shared helpers for uploading an attachment, building
  its authenticated URL and deciding whether it can be rendered inline.

### Changed

- The create-ticket form was reworked: wider category/subject/priority fields, explicit
  labels, and a visible hover/focus state on the support list rows.

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
