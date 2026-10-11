<?php

namespace Cengizkemec\SupportTickets\Notification;

use Cengizkemec\SupportTickets\Model\Ticket;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class NewTicketBlueprint implements BlueprintInterface, MailableInterface
{
    public function __construct(public Ticket $ticket)
    {
    }

    public function getFromUser()
    {
        return $this->ticket->user;
    }

    public function getSubject()
    {
        return $this->ticket;
    }

    public function getData()
    {
        return null;
    }

    public static function getType(): string
    {
        return 'newTicket';
    }

    public static function getSubjectModel(): string
    {
        return Ticket::class;
    }

    public function getEmailView(TranslatorInterface $translator): array
    {
        return [
            'text' => 'cengizkemec-support::emails.newTicket',
            'html' => 'cengizkemec-support::emails.newTicketHtml',
        ];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('cengizkemec-support.email.new_ticket_subject', [
            'subject' => $this->ticket->subject,
        ]);
    }
}
