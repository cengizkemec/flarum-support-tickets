<?php

namespace Cengizkemec\SupportTickets\Notification;

use Cengizkemec\SupportTickets\Model\Ticket;
use Flarum\Database\AbstractModel;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;
use Symfony\Contracts\Translation\TranslatorInterface;

class NewTicketBlueprint implements BlueprintInterface, MailableInterface, AlertableInterface
{
    public function __construct(public Ticket $ticket)
    {
    }

    public function getFromUser(): ?User
    {
        return $this->ticket->user;
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->ticket;
    }

    public function getData(): mixed
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

    public function getEmailViews(): array
    {
        return [
            'text' => 'cengizkemec-support::emails.plain.newTicket',
            'html' => 'cengizkemec-support::emails.html.newTicket',
        ];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('cengizkemec-support.email.new_ticket_subject', [
            'subject' => $this->ticket->subject,
        ]);
    }
}
