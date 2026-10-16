<x-mail::html.notification>
    <x-slot:body>
        {!! $formatter->convert($translator->trans('cengizkemec-support.email.ticket_reply.html.body', [
            '{author_display_name}' => $blueprint->reply->user->display_name,
            '{title}' => $blueprint->reply->ticket->subject,
            '{content}' => $blueprint->reply->message,
            '{url}' => $url->to('forum')->base().\Cengizkemec\SupportTickets\Support\TicketSlug::path($blueprint->reply->ticket->id, $blueprint->reply->ticket->subject),
        ])) !!}
    </x-slot:body>
</x-mail::html.notification>
