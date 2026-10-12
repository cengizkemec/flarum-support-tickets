<?php

namespace Cengizkemec\SupportTickets\Notification;

use Cengizkemec\SupportTickets\Model\Ticket;
use Cengizkemec\SupportTickets\Model\TicketReply;
use Flarum\Database\AbstractModel;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;
use Symfony\Contracts\Translation\TranslatorInterface;

class TicketReplyBlueprint implements BlueprintInterface, MailableInterface
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
        return ['replyId' => $this->reply->id];
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
            'text' => 'cengizkemec-support::emails.ticketReply',
            'html' => 'cengizkemec-support::emails.ticketReply',
        ];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('cengizkemec-support.email.reply_subject', [
            'subject' => $this->reply->ticket->subject,
        ]);
    }
}
