New support ticket: {{ $blueprint->ticket->subject }}

From: {{ $blueprint->ticket->user->display_name }}
Priority: {{ $blueprint->ticket->priority }}

{{ $blueprint->ticket->message }}

View: {{ url('/support/' . $blueprint->ticket->id) }}
