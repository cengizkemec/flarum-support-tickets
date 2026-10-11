# Flarum 2.0 HESK-Style Support Ticket Extension

Full-featured support ticket system for Flarum 2.0 (cengizkemec/support-tickets).

## Features
- Private tickets (only owner + staff)
- Categories (CRUD in admin)
- Priorities (Low / Medium / High / Critical)
- Statuses (New / Answered / Pending / Resolved / Closed)
- Staff assignment
- Replies
- Attachment upload (files stored in public/assets/support-tickets)
- Email + alert notifications
- Turkish + English locales
- Sidebar link
- Admin settings (auto-close days, email notifications)

## Installation

```bash
composer require cengizkemec/support-tickets:*@dev
php flarum migrate
php flarum cache:clear
```

Frontend is pre-built in `js/dist/`.

## Permissions
- `support.viewAll` — Moderators (see all tickets)
- `support.reply` — Members
- `support.manage` — Admins

## Notes
- Method signatures (`creating`, `scope`) are compatible with Flarum 2.0 `AbstractResource` / `Tobyz\JsonApiServer\Context`.
- Attachment upload is implemented via `POST /api/support/upload`.
- Email notifications use Flarum Notification blueprints.
- Auto-close setting is prepared; add a scheduled command for production use.

Migration filenames follow Flarum required format.
