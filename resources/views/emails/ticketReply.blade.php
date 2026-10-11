New reply on ticket: {{ $blueprint->reply->ticket->subject }}

From: {{ $blueprint->reply->user->display_name }}

{{ $blueprint->reply->message }}

View: {{ url('/support/' . $blueprint->reply->ticket->id) }}
