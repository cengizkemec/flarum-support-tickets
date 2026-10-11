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

        $extension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
        $filename = Str::random(40) . '.' . $extension;
        $directory = $this->paths->public . '/assets/support-tickets';

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $file->moveTo($directory . '/' . $filename);

        $url = '/assets/support-tickets/' . $filename;

        return new JsonResponse([
            'url' => $url,
            'filename' => $file->getClientFilename(),
        ]);
    }
}
