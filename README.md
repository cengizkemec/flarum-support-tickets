# Support Tickets for Flarum 2

[![Latest Version](https://img.shields.io/badge/version-1.1.1-blue.svg)](https://github.com/cengizkemec/flarum-support-tickets/releases)
[![Flarum](https://img.shields.io/badge/flarum-%5E2.0-orange.svg)](https://flarum.org)
[![PHP](https://img.shields.io/badge/php-%5E8.3-8892bf.svg)](https://www.php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

A full-featured, HESK-style support ticket system for **Flarum 2.0**.
Users open private tickets, staff manage them from the forum or the admin panel,
and everything is permission-driven and fully translatable.

Extension ID: `cengizkemec/support-tickets`

---

## Table of contents

- [Requirements](#requirements)
- [Features](#features)
- [Installation](#installation)
- [Permissions](#permissions)
- [Admin panel](#admin-panel)
- [Ticket lifecycle](#ticket-lifecycle)
- [File attachments](#file-attachments)
- [Auto-close](#auto-close)
- [Email notifications](#email-notifications)
- [API reference](#api-reference)
- [Security notes](#security-notes)
- [Updating](#updating)
- [Building the frontend](#building-the-frontend)
- [Localization](#localization)
- [Changelog](#changelog)
- [License](#license)

---

## Requirements

| Requirement | Version |
|-------------|---------|
| Flarum       | `^2.0` |
| PHP          | `^8.3` (tested on PHP 8.4) |
| Database     | MySQL / MariaDB (also works on SQLite and PostgreSQL) |

---

## Features

- **Private tickets** — only the owner and staff with the right permission can see a ticket.
- **Categories** — full CRUD from the admin panel, drag-free ordering by position,
  active/inactive toggle. Inactive categories are hidden from users.
- **Priorities** — Low / Medium / High / Critical.
- **Statuses** — New / Answered / Pending / Resolved / Closed.
- **Staff assignment** — assign a ticket to any staff member (permission-controlled).
- **Replies** — threaded conversation with protection against replies on closed tickets.
- **Secure attachments** — private storage plus authenticated downloads.
- **Notifications** — in-forum alerts and e-mail (e-mail can be toggled off).
- **Redesigned frontend** — hero header, summary cards, filter tabs, ticket cards and a
  chat-style ticket view, with dark-mode support.
- **Full i18n** — every string is translatable; English and Turkish ship out of the box.
- **Admin panel** — settings page with an inline category manager.
- **Scheduled auto-close** — automatically close inactive tickets.

---

## Installation

```bash
composer require cengizkemec/support-tickets
php flarum migrate
php flarum assets:publish
php flarum cache:clear
```

Then enable the extension (`Admin → Extensions → Support Tickets`).
The pre-built frontend lives in `js/dist/`, so no build step is needed to install.

---

## Permissions

Configure them under `Admin → Permissions`.

| Permission        | Description | Suggested default |
|-------------------|-------------|-------------------|
| `support.viewAll` | See and read every ticket, including other users' tickets. | Moderators |
| `support.reply`   | Post replies to tickets. Owners can always reply to their own open tickets. | Members |
| `support.manage`  | Change status, assign or delete tickets. | Moderators |

Users **without** these permissions only see and can only reply to their own tickets.

Two forum attributes are exposed to the frontend so the UI can adapt:
`canSupportViewAll`, `canSupportManage`, and `supportStaff` (the list of assignable staff).

---

## Admin panel

`Admin → Extensions → Support Tickets` gives you:

### Settings

- **Auto-close after (days)** — tickets with no activity for this many days are closed
  automatically. Allowed values: `1–365`. Default: `14`.
- **Email notifications** — send an e-mail when a ticket is created or a reply is posted.

Both settings save through Flarum's standard settings endpoint.

### Categories

An inline manager to create, edit, toggle and delete ticket categories.

| Field | Notes |
|-------|-------|
| **Name** | Required. |
| **Slug** | Required, unique, URL-friendly. Auto-generated from the name with Turkish-aware transliteration, and editable. |
| **Description** | Optional. |
| **Active** | Inactive categories are hidden from the "New Ticket" form. |

Feedback is shown inline (success/error alerts) and empty/loading/error states are handled.

---

## Ticket lifecycle

1. A user opens `New Ticket`, picks an active category, writes a subject and a message,
   and may attach a file.
2. The ticket is created with status **New** and priority **Medium**.
3. Staff reply, change the priority, assign the ticket and move it through the statuses.
4. **Resolved** and **Closed** tickets reject replies from non-staff until reopened.

---

## File attachments

- Uploaded through `POST /api/support/upload`.
- Stored privately in `storage/support-tickets/` — **not** web-accessible.
- Downloaded through the authenticated endpoint `GET /api/support/attachments/{filename}`.
- Only users who can view the parent ticket can download the file.
- Allowed types: JPEG, PNG, GIF, WebP, PDF, plain text, ZIP.
- Maximum size: **5 MB**.
- The real content type is verified server-side and dangerous filenames are rejected.

---

## Auto-close

The `auto_close_days` setting controls automatic closing of inactive tickets. A console
command is provided:

```bash
php flarum support-tickets:auto-close
```

It is registered with Flarum's scheduler and runs daily. Make sure your server has a cron
entry:

```
* * * * * cd /path-to-flarum && php flarum schedule:run >> /dev/null 2>&1
```

Tickets that are not already `closed` or `resolved` and that have had no activity for the
configured number of days are closed. The command is idempotent.

---

## Email notifications

Controlled by the `email_notifications` setting. When disabled, no e-mails are sent.
Notification failures never block ticket creation. In-forum (alert) notifications are always
created for the relevant parties.

---

## API reference

All endpoints are JSON:API resources under `/api` and require authentication where appropriate.

| Method | Endpoint | Type | Notes |
|--------|----------|------|-------|
| `GET` | `/api/tickets` | `tickets` | Supports `sort=createdAt`, `sort=-createdAt`, `sort=updatedAt`, `sort=-updatedAt`. Filtered by the actor's permissions. |
| `POST` | `/api/tickets` | `tickets` | `user_id` is always set server-side from the authenticated actor. |
| `PATCH` | `/api/tickets/{id}` | `tickets` | Status, priority and assignee changes require staff permissions. |
| `DELETE` | `/api/tickets/{id}` | `tickets` | Requires `support.manage`. |
| `GET` / `POST` | `/api/ticket-categories` | `ticket-categories` | Fields: `name`, `slug` (unique), `description`, `position`, `isActive`. |
| `GET` / `POST` | `/api/ticket-replies` | `ticket-replies` | The `ticket` relationship is required on create. |
| `POST` | `/api/support/upload` | — | Multipart file upload; returns the stored filename. |
| `GET` | `/api/support/attachments/{filename}` | — | Authenticated download; 403/404 on failure. |

---

## Security notes

- `user_id` is always set server-side from the authenticated actor.
- Status, priority and assignee changes require staff permissions.
- Ticket and reply access is enforced by policy **and** resource scope — not only in the frontend.
- Direct URL access to attachments is denied without authorization.
- Invalid status/priority values are rejected.

---

## Updating

```bash
composer update cengizkemec/support-tickets
php flarum migrate
php flarum assets:publish
php flarum cache:clear
```

Your data is preserved across updates. Attachments uploaded before `1.0.2` that were stored
in `public/assets/` need a manual move into `storage/support-tickets/` if you want them private.

---

## Building the frontend

Frontend sources live in `js/src/`. To rebuild after changing them:

```bash
cd js
npm install
npm run build
```

The compiled bundles (`js/dist/forum.js`, `js/dist/admin.js`) are committed to the repository.

---

## Localization

Locale files are in `resources/locale/`:

```
resources/locale/en.yml
resources/locale/tr.yml
```

Every user-facing string (including all admin strings) uses a translation key, so adding a new
locale is a matter of copying `en.yml`, translating the values, and placing it in the same folder.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full release history.

---

## License

MIT — see [LICENSE](LICENSE).
