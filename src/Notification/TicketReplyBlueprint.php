<?php

namespace Cengizkemec\SupportTickets\Notification;

use Cengizkemec\SupportTickets\Model\Ticket;
use Cengizkemec\SupportTickets\Model\TicketReply;
use Flarum\Database\AbstractModel;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;
use Symfony\Contracts\Translation\TranslatorInterface;

class TicketReplyBlueprint implements BlueprintInterface, MailableInterface, AlertableInterface
{
    public function __construct(public TicketReply $reply)
    {
    }

    public function getFromUser(): ?User
    {
        return $this->reply->user;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->reply->ticket;
    }

    public function getData(): mixed
    {
        return [
            'ticketId' => $this->reply->ticket_id,
            'replyId' => $this->reply->id,
            'slug' => $this->reply->ticket ? $this->reply->ticket->slug() : '',
        ];
    }

    public static function getType(): string
    {
        return 'ticketReply';
    }

    public static function getSubjectModel(): string
    {
        return Ticket::class;
    }

    public function getEmailViews(): array
    {
        return [
            'text' => 'cengizkemec-support::emails.plain.ticketReply',
            'html' => 'cengizkemec-support::emails.html.ticketReply',
        ];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('cengizkemec-support.email.reply_subject', [
            'subject' => $this->reply->ticket->subject,
        ]);
    }
}
