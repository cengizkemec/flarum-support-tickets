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
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
        'text/plain',
        'application/zip',
        'application/x-zip-compressed',
    ];

    private const MIME_TO_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'application/zip' => 'zip',
        'application/x-zip-compressed' => 'zip',
    ];

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
        // Reject null bytes and path traversal attempts in client name
        if (str_contains($clientName, "\0") || str_contains($clientName, '..') || str_contains($clientName, '/') || str_contains($clientName, '\\')) {
            return new JsonResponse(['error' => 'Invalid filename'], 400);
        }

        $tmpPath = $file->getStream()->getMetadata('uri');
        if (!$tmpPath || !is_readable($tmpPath)) {
            return new JsonResponse(['error' => 'Cannot read uploaded file'], 400);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath) ?: '';

        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            return new JsonResponse(['error' => 'Invalid file content type'], 400);
        }

        $extension = self::MIME_TO_EXT[$mime] ?? null;
        if (!$extension) {
            return new JsonResponse(['error' => 'Unsupported file type'], 400);
        }

        // Reject double extensions even if MIME matches
        $baseName = pathinfo($clientName, PATHINFO_FILENAME);
        if (preg_match('/\.(php|phtml|phar|html|htm|js|svg|exe|bat|sh)$/i', $baseName)) {
            return new JsonResponse(['error' => 'Dangerous file name'], 400);
        }

        $filename = Str::random(40) . '.' . $extension;
        $directory = $this->paths->storage . '/support-tickets';

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0750, true) && !is_dir($directory)) {
                return new JsonResponse(['error' => 'Cannot create storage directory'], 500);
            }
        }

        $target = $directory . '/' . $filename;
        $file->moveTo($target);

        // Ensure the file is not executable
        chmod($target, 0640);

        return new JsonResponse([
            'filename' => $filename,
            'originalName' => basename($clientName),
        ]);
    }
}
