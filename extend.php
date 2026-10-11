<?php

use Cengizkemec\SupportTickets\Access\TicketPolicy;
use Cengizkemec\SupportTickets\Api\Controller\UploadAttachmentController;
use Cengizkemec\SupportTickets\Api\Resource\TicketCategoryResource;
use Cengizkemec\SupportTickets\Api\Resource\TicketReplyResource;
use Cengizkemec\SupportTickets\Api\Resource\TicketResource;
use Cengizkemec\SupportTickets\Listener\SendTicketNotifications;
use Cengizkemec\SupportTickets\Model\Ticket;
use Cengizkemec\SupportTickets\Notification\NewTicketBlueprint;
use Cengizkemec\SupportTickets\Notification\TicketReplyBlueprint;
use Flarum\Extend;

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
        ->namespace('cengizkemec-support', __DIR__.'/resources/views'),

    (new Extend\Settings())
        ->serializeToForum('supportAutoCloseDays', 'cengizkemec-support.auto_close_days', 14)
        ->default('cengizkemec-support.auto_close_days', 14)
        ->default('cengizkemec-support.email_notifications', true),
];
