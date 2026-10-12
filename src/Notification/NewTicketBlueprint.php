<?php

namespace Cengizkemec\SupportTickets\Notification;

use Cengizkemec\SupportTickets\Model\Ticket;
use Flarum\Database\AbstractModel;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;
use Symfony\Contracts\Translation\TranslatorInterface;

class NewTicketBlueprint implements BlueprintInterface, MailableInterface
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
            'text' => 'cengizkemec-support::emails.newTicket',
            'html' => 'cengizkemec-support::emails.newTicket',
        ];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('cengizkemec-support.email.new_ticket_subject', [
            'subject' => $this->ticket->subject,
        ]);
    }
}
