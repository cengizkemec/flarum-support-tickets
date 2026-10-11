# Flarum 2.0 HESK-Style Support Ticket Extension

Full-featured support ticket system for Flarum 2.0.

## Features
- Private tickets (only owner + staff)
- Categories (CRUD in admin)
- Priorities (Low / Medium / High / Critical)
- Statuses (New / Answered / Pending / Resolved / Closed)
- Staff assignment
- Replies
- Attachment field (ready for filesystem / fof/upload integration)
- Turkish + English locales
- Sidebar link
- Admin settings (auto-close days, email notifications)
- Auto-close setting prepared

## Installation

```bash
composer require acme/support-tickets:*@dev
php flarum migrate
php flarum cache:clear

# Build frontend
cd js && yarn && yarn build
```

## Permissions
- `support.viewAll` — Moderators (see all tickets)
- `support.reply` — Members
- `support.manage` — Admins

## Notes
- Attachment upload is prepared (filename stored). For real file storage integrate Flarum\Filesystem or fof/upload.
- Email notifications: setting exists; implement Blueprint + Notification extender for full mail.
- Auto-close: setting exists; add a scheduled command / queue job for production.

Migration filenames follow Flarum required format.
