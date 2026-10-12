# Changelog

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
