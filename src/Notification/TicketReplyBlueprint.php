<?php

namespace Acme\SupportTickets\Notification;

use Acme\SupportTickets\Model\TicketReply;
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
        return \Acme\SupportTickets\Model\Ticket::class;
    }

    public function getEmailView(TranslatorInterface $translator): array
    {
        return [
            'text' => 'acme-support::emails.ticketReply',
            'html' => 'acme-support::emails.ticketReplyHtml',
        ];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('acme-support.email.reply_subject', [
            'subject' => $this->reply->ticket->subject,
        ]);
    }
}
