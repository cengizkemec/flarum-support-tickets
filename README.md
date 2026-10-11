# Flarum 2.0 HESK-Style Support Ticket Extension

## Installation

```bash
composer require acme/support-tickets:*@dev
php flarum migrate
php flarum cache:clear
```

## Features
- Private support tickets
- Categories, priorities, statuses
- Turkish + English locales
- Staff assignment ready
- Sidebar integration

**Note:** This is a production-oriented skeleton. Attachment handling, full email notifications, and auto-close jobs require additional implementation.

Migration filenames follow Flarum's required `YYYY_MM_DD_HHMMSS_description.php` format.
