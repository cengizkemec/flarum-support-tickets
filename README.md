# Support Tickets for Flarum 2

[![Latest Version](https://img.shields.io/badge/version-1.1.8-blue.svg)](https://github.com/cengizkemec/flarum-support-tickets/releases)
[![Flarum](https://img.shields.io/badge/flarum-%5E2.0-orange.svg)](https://flarum.org)
[![PHP](https://img.shields.io/badge/php-%5E8.3-8892bf.svg)](https://www.php.net)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

A full-featured, HESK-style support ticket system for **Flarum 2.0**.
Users open private tickets, staff manage them from the forum or the admin panel, tickets are
routed to **departments**, written with **Flarum's own rich text editor**, and everything is
permission-driven and fully translatable.

Extension ID: `cengizkemec/support-tickets`

---

## Table of contents

- [Requirements](#requirements)
- [Features](#features)
- [Screenshots](#screenshots)
- [Installation](#installation)
- [Permissions](#permissions)
- [Admin panel](#admin-panel)
- [Departments](#departments)
- [Ticket lifecycle](#ticket-lifecycle)
- [File attachments](#file-attachments)
- [Scheduled maintenance](#scheduled-maintenance)
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
- **Departments** — split the support team into departments from the admin panel, add members
  with a user search, and link each category to the department that answers it. New tickets are
  auto-assigned inside the department and only its members are notified. See
  [Departments](#departments).
- **Rich text editor** — ticket messages and replies are written with Flarum's own composer
  editor, so the toolbar, keyboard shortcuts (Ctrl+B/I, Ctrl+Enter) and emoji autocomplete come
  from whatever formatting extensions the forum runs (markdown, BBCode, emoji, ...). Messages
  are stored as plain text and rendered with the same server-side formatter posts use.
- **Categories** — full CRUD from the admin panel, drag-free ordering by position,
  active/inactive toggle, optional department link. Inactive categories are hidden from users.
- **Custom ticket form fields (HESK style)** — define your own fields (text, textarea,
  number, email, url, date, select, radio, checkbox) from the admin panel, either global or
  scoped to one category, with required/active flags and ordering. Answers are validated
  server-side and shown inside the ticket.
- **Priorities** — Low / Medium / High / Critical.
- **Statuses** — New / Answered / Pending / Resolved / Closed.
- **Staff assignment** — assign a ticket to any staff member (permission-controlled), or let
  the extension auto-assign new tickets to the configured **support team group** — or, when the
  category has a department, to the department member with the fewest open tickets.
- **Readable ticket URLs** — tickets are linked as `/support/18/ses-ve-mikrofon-sorunu`
  (Turkish-aware slug), while the bare `/support/18` keeps working.
- **Replies** — threaded conversation with protection against replies on closed tickets.
- **Secure attachments** — private storage plus authenticated downloads.
- **Notifications** — in-forum alerts, e-mail (e-mail can be toggled off) and **FoF PWA web
  push links**. Notifications about a ticket in a department-linked category go to that
  department's members only. Ticket notifications render with an icon, the sender, the action
  text and an excerpt of the ticket subject, and link straight to the ticket. Users can enable
  or disable each type.
- **Guest-friendly support centre** — guests landing on `/support` get a clear invitation to
  sign in or register, and both buttons open the standard Flarum modals. "Support" stays
  visible in the sidebar for everyone.
- **"Back to Support" link** on every ticket page, plus a note with a back button on closed
  tickets, so nobody gets stuck inside a ticket.
- **Redesigned frontend** — hero header, summary cards, filter tabs, ticket cards and a
  chat-style ticket view, with dark-mode support.
- **Full i18n** — every string is translatable; English and Turkish ship out of the box.
  Browser-tab titles follow the page (support centre and ticket subject).
- **Admin panel** — settings page with an inline category manager, an inline form-field
  manager, a departments manager with a member picker and the support team group selector.
- **Scheduled maintenance** — automatic closing of inactive tickets and purging of orphaned
  attachments.

---

## Screenshots

Screenshots below were taken on a live Flarum 2.0.1 forum running this extension.

| Support centre | Ticket view |
|----------------|-------------|
| ![Support centre](docs/images/support-center.png) | ![Ticket view](docs/images/ticket-view.png) |

| Notifications |   |
|---------------|---|
| ![Ticket notifications](docs/images/notifications.png) |

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
- **Support team group** — members of the selected group are the support team: they see every
  ticket, receive the notifications and can be auto-assigned new tickets. When nothing is
  selected, administrators and holders of `support.viewAll` are used.

All settings save through Flarum's standard settings endpoint.

### Categories

An inline manager to create, edit, toggle and delete ticket categories.

| Field | Notes |
|-------|-------|
| **Name** | Required. |
| **Slug** | Required, unique, URL-friendly. Auto-generated from the name with Turkish-aware transliteration, and editable. |
| **Description** | Optional. |
| **Department** | Optional. Links the category to a department so its tickets are routed there. The table has a per-row select that saves instantly. |
| **Active** | Inactive categories are hidden from the "New Ticket" form. |

Feedback is shown inline (success/error alerts) and empty/loading/error states are handled.

### Departments

An inline manager to create, edit, toggle and delete departments, with a member picker.

| Field | Notes |
|-------|-------|
| **Name** | Required. |
| **Slug** | Required, unique. Auto-generated from the name, and editable. |
| **Description** | Optional. |
| **Members** | Opens a picker: search users by name or username, add and remove members, save. Only administrators receive the member list from the API. |
| **Active** | Inactive departments no longer receive tickets; their categories fall back to the general support team. |

Deleting a department unlinks its categories first, so nothing points at a departed department.

### Form fields

"Ticket form fields" extends the New Ticket form with your own questions, HESK style.

| Field | Notes |
|-------|-------|
| **Label** | Shown to users on the ticket form. |
| **Key** | Internal identifier (letters, numbers, underscores) used as the key in `tickets.custom_fields`. Auto-generated from the label when left empty. |
| **Type** | `text`, `textarea`, `number`, `email`, `url`, `date`, `select`, `radio`, `checkbox`. |
| **Choices** | For `select` / `radio` / `checkbox`: one choice per line. |
| **Category** | Leave empty for a global field, or pick a category to show it only there. |
| **Required** | Enforced in the New Ticket form and validated server-side. |
| **Active** | Inactive fields disappear from the form but stored answers stay visible in tickets. |

Answers are sanitised server-side per type (numbers coerced, e-mail/URL/date formats checked,
choices validated against the list) and displayed as label/value rows inside the ticket.

---

## Departments

Departments are groups of support staff used to route tickets. Everything is managed from
`Admin → Extensions → Support Tickets → Departments`:

1. **Create a department** and give it a name (a slug is generated automatically).
2. **Add members** with the member picker — the people who should see and answer tickets in
   this department.
3. **Link categories** to the department from the category manager (create form or the
   per-row select on the category table).

From then on the department drives the routing:

- A new ticket in a linked category is **auto-assigned to the department member with the
  fewest open tickets** (when auto-assign is enabled).
- **Notifications** for new tickets and for requester replies go only to that department's
  members, plus the assignee — not to the whole support team.
- The department is shown as a chip on the ticket list and the ticket page, and the New Ticket
  modal tells the user which department answers the selected category.
- A department that is empty or deactivated falls back to the general support team, and
  deleting a department unlinks its categories, so tickets are never lost.

> Department membership routes tickets; it does not grant access by itself. Make sure members
> hold the support permissions (or belong to the configured support team group) so they can
> open the tickets assigned to them.

---

## Ticket lifecycle

1. A user opens `New Ticket`, picks an active category, writes a subject and a message,
   answers the custom form fields configured for that category (if any), and may attach a file.
2. The ticket is created with status **New** and priority **Medium**, and — when auto-assign
   is enabled — handed to a staff member: inside the category's department when it has one,
   otherwise across the whole support team.
3. Staff reply, change the priority, assign the ticket and move it through the statuses.
   Notifications follow the department when the category is linked to one.
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
- Stored under a random 40-character name, so a crafted upload filename cannot escape the
  storage directory.
- Images are displayed inline inside the ticket conversation (screenshots pasted with
  Ctrl+V show up straight away); every other type is offered as a download.

---

## Scheduled maintenance

Two console commands are provided, both registered with Flarum's scheduler and run daily.
Make sure your server has a cron entry:

```
* * * * * cd /path-to-flarum && php flarum schedule:run >> /dev/null 2>&1
```

### Auto-close

The `auto_close_days` setting controls automatic closing of inactive tickets:

```bash
php flarum support-tickets:auto-close
```

Tickets that are not already `closed` or `resolved` and that have had no activity for the
configured number of days are closed. The command is idempotent.

### Purging orphaned attachments

```bash
php flarum support-tickets:purge-attachments
```

Deletes attachments that are no longer referenced by any ticket or reply — for example an
upload that was submitted but whose ticket was later deleted. Files younger than 24 hours are
skipped, so an upload that is still being submitted is never removed out from under the user.

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
| `GET` / `POST` | `/api/ticket-categories` | `ticket-categories` | Fields: `name`, `slug` (unique), `description`, `departmentId` (nullable, validated), `position`, `isActive`. The `department` relationship is includable. |
| `GET` / `POST` / `PATCH` / `DELETE` | `/api/support-departments` | `support-departments` | Admin-only mutations. Fields: `name`, `slug` (unique), `description`, `userIds` (member ids, only serialised for administrators), `isActive`. |
| `GET` / `POST` / `PATCH` / `DELETE` | `/api/ticket-form-fields` | `ticket-form-fields` | Fields: `label`, `name`, `type`, `options` (array, for `select`/`radio`/`checkbox`), `isRequired`, `isActive`, `position`, `category`. Sortable by `position`. |
| `GET` / `POST` | `/api/ticket-replies` | `ticket-replies` | The `ticket` relationship is required on create. |
| `POST` | `/api/support/upload` | — | Multipart file upload; returns the stored filename. |
| `GET` | `/api/support/attachments/{filename}` | — | Authenticated download; 403/404 on failure. |

Tickets and replies carry a read-only `messageHtml` attribute: the stored plain text rendered
by the same server-side s9e formatter posts use (lists, quotes, code, links), which is what the
forum displays.

---

## Security notes

- `user_id` is always set server-side from the authenticated actor.
- Status, priority, assignee, subject, message, attachment, category and owner are all
  protected: a non-staff owner cannot change them through a crafted `PATCH`.
- Ticket and reply access is enforced by policy **and** resource scope — not only in the
  frontend.
- Direct URL access to attachments is denied without authorization.
- Attachment downloads send `X-Content-Type-Options: nosniff`,
  `Content-Security-Policy: default-src 'none'; sandbox` and `Cache-Control: private`.
  Only raster images are served `inline`; everything else is forced to download.
- Uploaded files are stored under a random 40-character name derived from the verified MIME
  type, so a crafted filename cannot escape the storage directory or change the extension.
- Message bodies are capped at **15,000 characters**, and category, department and form-field
  inputs have explicit length/size bounds, so a crafted request cannot exceed its column or
  force the formatter to digest an enormous body.
- A ticket's `attachment` can only ever hold a server-generated filename — crafted values are
  refused on create **and** on update, even for staff, and the stored value is kept.
- Formatted message HTML is produced exclusively by the core s9e formatter (the same trust
  chain as posts) and rendered with `m.trust` exactly like core posts; list excerpts are
  parsed with `DOMParser`, and the fallback rendering escapes the raw text.
- `ticket_categories.department_id` is validated with `exists:support_departments,id`; the
  `userIds` of a department are filtered against the users table and only serialised for
  administrators.
- Invalid status/priority values are rejected, and replies cannot spoof `isStaff`.
- Covered by an automated security regression suite (guest access, cross-user isolation,
  mass-assignment, `isStaff` spoofing, download headers, upload validation including
  double-extension and path-traversal payloads, and storage safety) — 33 checks, all passing.

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
