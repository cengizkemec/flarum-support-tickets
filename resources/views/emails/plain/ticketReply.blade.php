<x-mail::plain.notification>
<x-slot:body>
{!! $translator->trans('cengizkemec-support.email.ticket_reply.body', [
    '{author_display_name}' => $blueprint->reply->user->display_name,
    '{title}' => $blueprint->reply->ticket->subject,
    '{content}' => $blueprint->reply->message,
    '{url}' => $url->to('forum')->base().'/support/'.$blueprint->reply->ticket->id,
]) !!}
</x-slot:body>
</x-mail::plain.notification>
