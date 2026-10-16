<?php

use Cengizkemec\SupportTickets\Access\TicketPolicy;
use Cengizkemec\SupportTickets\Api\Controller\DownloadAttachmentController;
use Cengizkemec\SupportTickets\Api\Controller\UploadAttachmentController;
use Cengizkemec\SupportTickets\Api\Resource\TicketCategoryResource;
use Cengizkemec\SupportTickets\Api\Resource\SupportDepartmentResource;
use Cengizkemec\SupportTickets\Api\Resource\TicketFormFieldResource;
use Cengizkemec\SupportTickets\Api\Resource\TicketReplyResource;
use Cengizkemec\SupportTickets\Api\Resource\TicketResource;
use Cengizkemec\SupportTickets\Console\AutoCloseTicketsCommand;
use Cengizkemec\SupportTickets\Console\PurgeOrphanAttachmentsCommand;
use Cengizkemec\SupportTickets\Listener\SendTicketNotifications;
use Cengizkemec\SupportTickets\Model\Ticket;
use Cengizkemec\SupportTickets\Notification\NewTicketBlueprint;
use Cengizkemec\SupportTickets\Notification\TicketReplyBlueprint;
use Cengizkemec\SupportTickets\Pwa\PwaServiceProvider;
use Cengizkemec\SupportTickets\Support\TicketStaff;
use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\User\User;
use Illuminate\Console\Scheduling\Event;
use Tobyz\JsonApiServer\Context;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->route('/support', 'support')
        // The trailing slug is optional (FastRoute bracket syntax, the same
        // way core registers /d/{id}[/{near}]), so /support/18 and
        // /support/18/ses-ve-mikrofon-sorunu both resolve to the same ticket.
        ->route('/support/{id:\d+}[/{slug}]', 'support.ticket'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/resources/locale'),

    (new Extend\Routes('api'))
        ->post('/support/upload', 'support.upload', UploadAttachmentController::class)
        ->get('/support/attachments/{filename}', 'support.attachment.download', DownloadAttachmentController::class),

    new Extend\ApiResource(TicketResource::class),
    new Extend\ApiResource(TicketCategoryResource::class),
    new Extend\ApiResource(SupportDepartmentResource::class),
    new Extend\ApiResource(TicketFormFieldResource::class),
    new Extend\ApiResource(TicketReplyResource::class),

    // Expose staff capabilities to the frontend as forum attributes:
    // app.forum.attribute('canSupportViewAll') / ('canSupportManage') / ('supportStaff').
    (new Extend\ApiResource(ForumResource::class))
        ->fields(fn () => [
            Schema\Boolean::make('canSupportViewAll')
                ->get(fn ($model, Context $context) => $context->getActor()->can('support.viewAll')),
            Schema\Boolean::make('canSupportManage')
                ->get(fn ($model, Context $context) => $context->getActor()->can('support.manage')),
            Schema\Arr::make('supportStaff')
                ->get(function ($model, Context $context) {
                    $actor = $context->getActor();

                    if (! $actor->can('support.viewAll') && ! $actor->can('support.manage')) {
                        return [];
                    }

                    return resolve(TicketStaff::class)
                        ->members()
                        ->sortBy('username')
                        ->take(200)
                        ->map(fn (User $user) => [
                            'id' => (int) $user->id,
                            'username' => $user->username,
                            'displayName' => $user->display_name,
                            'avatarUrl' => $user->avatar_url,
                        ])
                        ->values()
                        ->all();
                }),
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
        ->default('cengizkemec-support.email_notifications', true)
        ->default('cengizkemec-support.staff_group_id', null)
        ->default('cengizkemec-support.auto_assign', true),

    // Make FoF PWA open the ticket (rather than the board index) when a push
    // notification about a ticket is tapped. No-op when FoF PWA is absent.
    (new Extend\ServiceProvider())
        ->register(PwaServiceProvider::class),

    (new Extend\Console())
        ->command(AutoCloseTicketsCommand::class)
        ->schedule(AutoCloseTicketsCommand::class, function (Event $event) {
            $event->daily();
        })
        ->command(PurgeOrphanAttachmentsCommand::class)
        ->schedule(PurgeOrphanAttachmentsCommand::class, function (Event $event) {
            $event->daily();
        }),
];
