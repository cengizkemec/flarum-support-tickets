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
        // Carried through to the frontend so the notification can always link
        // to its ticket, even before the subject model is in the store. The
        // slug lets the frontend build the pretty /support/{id}/{slug} URL.
        return [
            'ticketId' => $this->ticket->id,
            'slug' => $this->ticket->slug(),
        ];
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
