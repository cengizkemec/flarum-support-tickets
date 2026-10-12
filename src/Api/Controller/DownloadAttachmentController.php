<?php

namespace Cengizkemec\SupportTickets\Api\Controller;

use Cengizkemec\SupportTickets\Model\Ticket;
use Cengizkemec\SupportTickets\Model\TicketReply;
use Flarum\Foundation\Paths;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DownloadAttachmentController implements RequestHandlerInterface
{
    public function __construct(protected Paths $paths)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $filename = $request->getAttribute('filename');
        if (!$filename || !preg_match('/^[a-zA-Z0-9_\-\.]+\.[a-zA-Z0-9]+$/', $filename)) {
            return new Response(null, 400);
        }

        // Prevent path traversal
        $filename = basename($filename);
        $fullPath = $this->paths->storage . '/support-tickets/' . $filename;

        if (!is_file($fullPath) || !is_readable($fullPath)) {
            return new Response(null, 404);
        }

        // Find which ticket/reply owns this attachment
        $ticket = Ticket::where('attachment', $filename)->first();
        $reply = null;
        if (!$ticket) {
            $reply = TicketReply::where('attachment', $filename)->first();
            if ($reply) {
                $ticket = $reply->ticket;
            }
        }

        if (!$ticket) {
            return new Response(null, 404);
        }

        // Authorization: must be able to view the ticket
        if (!$actor->can('view', $ticket)) {
            return new Response(null, 403);
        }

        $stream = new Stream($fullPath, 'r');
        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';

        return new Response($stream, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . basename($filename) . '"',
            'Content-Length' => (string) filesize($fullPath),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-cache',
        ]);
    }
}
