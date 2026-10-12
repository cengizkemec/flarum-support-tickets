# Flarum 2.0 HESK-Style Support Ticket Extension

Full-featured support ticket system for Flarum 2.0 (`cengizkemec/support-tickets`).

## Features
- Private tickets (only owner + staff with proper permissions)
- Categories (CRUD in admin)
- Priorities (Low / Medium / High / Critical)
- Statuses (New / Answered / Pending / Resolved / Closed)
- Staff assignment (permission-controlled)
- Replies (with closed-ticket protection)
- Secure attachment upload & authenticated download
- Email + alert notifications (controlled by setting)
- Turkish + English locales
- Sidebar link
- Admin settings (auto-close days, email notifications)
- Automatic ticket closing via scheduled command

## Installation

```bash
composer require cengizkemec/support-tickets
php flarum migrate
php flarum cache:clear
```

Frontend is pre-built in `js/dist/`. After code changes rebuild with:

```bash
cd js && npm install && npm run build
```

## Permissions
- `support.viewAll` — View all tickets (default: Moderators)
- `support.reply` — Reply to tickets (default: Members; owners can always reply to their own open tickets)
- `support.manage` — Manage tickets (status, assignment, delete)

Owners can only see and reply to their own tickets. Staff need the appropriate permission.

## File Attachments
- Uploaded via `POST /api/support/upload`
- Stored privately in `storage/support-tickets/` (not web-accessible)
- Downloaded via authenticated `GET /api/support/attachments/{filename}`
- Only users who can view the parent ticket can download the file
- Allowed types: JPEG, PNG, GIF, WebP, PDF, plain text, ZIP
- Max size: 5 MB
- Real content type is verified server-side; dangerous filenames are rejected

## Auto-Close
The `auto_close_days` setting (default 14) controls automatic closing of inactive tickets.

A console command is provided:

```bash
php flarum support-tickets:auto-close
```

It is scheduled to run daily via Flarum's scheduler. Ensure your server has a cron entry:

```
* * * * * cd /path-to-flarum && php flarum schedule:run >> /dev/null 2>&1
```

Tickets that are not already `closed` or `resolved` and have not been updated for the configured number of days will be closed. The command is idempotent.

## Email Notifications
Controlled by the `email_notifications` setting. When disabled, no emails are sent. Notification failures do not block ticket creation.

## Security Notes
- `user_id` is always set server-side from the authenticated actor
- Status, priority and assignee changes require staff permissions
- Ticket and reply access is enforced by policy + resource scope (not only frontend)
- Direct URL access to attachments is denied without authorization
- Invalid status/priority values are rejected

## Updating
```bash
composer update cengizkemec/support-tickets
php flarum migrate
php flarum cache:clear
```

Existing data is preserved. Attachments uploaded before 1.0.2 that were stored in `public/assets/` will need manual migration if you want them private.

## Version
1.0.2 — Security hardening, authenticated downloads, auto-close command, improved access control.
