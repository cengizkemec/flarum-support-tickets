<?php

namespace Cengizkemec\SupportTickets\Api\Controller;

use Flarum\Foundation\Paths;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UploadAttachmentController implements RequestHandlerInterface
{
    private const MAX_SIZE = 5 * 1024 * 1024; // 5 MB
    private const ALLOWED_MIME = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'text/plain',
        'application/zip', 'application/x-zip-compressed',
    ];
    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'zip'];

    public function __construct(protected Paths $paths)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $files = $request->getUploadedFiles();
        if (empty($files['file'])) {
            return new JsonResponse(['error' => 'No file uploaded'], 400);
        }

        $file = $files['file'];
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return new JsonResponse(['error' => 'Upload error'], 400);
        }

        if ($file->getSize() > self::MAX_SIZE) {
            return new JsonResponse(['error' => 'File too large (max 5MB)'], 400);
        }

        $clientName = $file->getClientFilename() ?? '';
        $extension = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));

        if (!in_array($extension, self::ALLOWED_EXT, true)) {
            return new JsonResponse(['error' => 'File type not allowed'], 400);
        }

        // Verify real content type
        $tmpPath = $file->getStream()->getMetadata('uri');
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath) ?: '';

        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            return new JsonResponse(['error' => 'Invalid file content'], 400);
        }

        $filename = Str::random(40) . '.' . $extension;
        $directory = $this->paths->public . '/assets/support-tickets';

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $file->moveTo($directory . '/' . $filename);

        $url = '/assets/support-tickets/' . $filename;

        return new JsonResponse([
            'url' => $url,
            'filename' => basename($clientName),
        ]);
    }
}
