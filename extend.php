<?php

use Acme\SupportTickets\Access\TicketPolicy;
use Acme\SupportTickets\Api\Controller\UploadAttachmentController;
use Acme\SupportTickets\Api\Resource\TicketCategoryResource;
use Acme\SupportTickets\Api\Resource\TicketReplyResource;
use Acme\SupportTickets\Api\Resource\TicketResource;
use Acme\SupportTickets\Listener\SendTicketNotifications;
use Acme\SupportTickets\Model\Ticket;
use Acme\SupportTickets\Notification\NewTicketBlueprint;
use Acme\SupportTickets\Notification\TicketReplyBlueprint;
use Flarum\Extend;
use Flarum\Group\Group;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->route('/support', 'support'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/resources/locale'),

    (new Extend\Routes('api'))
        ->post('/support/upload', 'support.upload', UploadAttachmentController::class),

    new Extend\ApiResource(TicketResource::class),
    new Extend\ApiResource(TicketCategoryResource::class),
    new Extend\ApiResource(TicketReplyResource::class),

    (new Extend\Policy())
        ->modelPolicy(Ticket::class, TicketPolicy::class),

    (new Extend\Notification())
        ->type(NewTicketBlueprint::class, ['alert', 'email'])
        ->type(TicketReplyBlueprint::class, ['alert', 'email']),

    (new Extend\Event())
        ->subscribe(SendTicketNotifications::class),

    (new Extend\View())
        ->namespace('acme-support', __DIR__.'/resources/views'),

    (new Extend\Settings())
        ->serializeToForum('supportAutoCloseDays', 'acme-support.auto_close_days', 14)
        ->default('acme-support.auto_close_days', 14)
        ->default('acme-support.email_notifications', true),

    (new Extend\Permissions())
        ->permission('support.viewAll', Group::MODERATOR_ID)
        ->permission('support.reply', Group::MEMBER_ID)
        ->permission('support.manage', Group::ADMINISTRATOR_ID),
];
