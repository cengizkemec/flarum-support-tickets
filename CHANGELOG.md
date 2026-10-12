# Changelog

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
