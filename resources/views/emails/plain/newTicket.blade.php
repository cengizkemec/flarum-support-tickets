<x-mail::plain.notification>
<x-slot:body>
{!! $translator->trans('cengizkemec-support.email.new_ticket.body', [
    '{opener_display_name}' => $blueprint->ticket->user->display_name,
    '{title}' => $blueprint->ticket->subject,
    '{priority}' => $blueprint->ticket->priority,
    '{content}' => $blueprint->ticket->message,
    '{url}' => $url->to('forum')->base().'/support/'.$blueprint->ticket->id,
]) !!}
</x-slot:body>
</x-mail::plain.notification>
