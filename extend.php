<?php

use Cengizkemec\SupportTickets\Access\TicketPolicy;
use Cengizkemec\SupportTickets\Api\Controller\DownloadAttachmentController;
use Cengizkemec\SupportTickets\Api\Controller\UploadAttachmentController;
use Cengizkemec\SupportTickets\Api\Resource\TicketCategoryResource;
use Cengizkemec\SupportTickets\Api\Resource\TicketReplyResource;
use Cengizkemec\SupportTickets\Api\Resource\TicketResource;
use Cengizkemec\SupportTickets\Console\AutoCloseTicketsCommand;
use Cengizkemec\SupportTickets\Listener\SendTicketNotifications;
use Cengizkemec\SupportTickets\Model\Ticket;
use Cengizkemec\SupportTickets\Notification\NewTicketBlueprint;
use Cengizkemec\SupportTickets\Notification\TicketReplyBlueprint;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Illuminate\Console\Scheduling\Event;
use Tobyz\JsonApiServer\Context;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->route('/support', 'support'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js'),

    new Extend\Locales(__DIR__.'/resources/locale'),

    (new Extend\Routes('api'))
        ->post('/support/upload', 'support.upload', UploadAttachmentController::class)
        ->get('/support/attachments/{filename}', 'support.attachment.download', DownloadAttachmentController::class),

    new Extend\ApiResource(TicketResource::class),
    new Extend\ApiResource(TicketCategoryResource::class),
    new Extend\ApiResource(TicketReplyResource::class),

    // Expose staff capabilities to the frontend as forum attributes:
    // app.forum.attribute('canSupportViewAll') / ('canSupportManage').
    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canSupportViewAll')
                ->get(fn ($model, Context $context) => $context->getActor()->can('support.viewAll')),
            Schema\Boolean::make('canSupportManage')
                ->get(fn ($model, Context $context) => $context->getActor()->can('support.manage')),
        ]),

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
        ->serializeToForum('supportAutoCloseDays', 'cengizkemec-support.auto_close_days')
        ->default('cengizkemec-support.auto_close_days', 14)
        ->default('cengizkemec-support.email_notifications', true),

    (new Extend\Console())
        ->command(AutoCloseTicketsCommand::class)
        ->schedule(AutoCloseTicketsCommand::class, function (Event $event) {
            $event->daily();
        }),
];
