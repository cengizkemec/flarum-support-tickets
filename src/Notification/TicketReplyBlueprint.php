<?php

namespace Cengizkemec\SupportTickets\Notification;

use Cengizkemec\SupportTickets\Model\TicketReply;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TicketReplyBlueprint implements BlueprintInterface, MailableInterface
{
    public function __construct(public TicketReply $reply)
    {
    }

    public function getFromUser()
    {
        return $this->reply->user;
    }

    public function getSubject()
    {
        return $this->reply->ticket;
    }

    public function getData()
    {
        return ['replyId' => $this->reply->id];
    }

    public static function getType(): string
    {
        return 'ticketReply';
    }

    public static function getSubjectModel(): string
    {
        return \Cengizkemec\SupportTickets\Model\Ticket::class;
    }

    public function getEmailView(TranslatorInterface $translator): array
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
