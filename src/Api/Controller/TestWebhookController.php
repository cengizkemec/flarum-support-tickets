<?php

namespace Cengizkemec\SupportTickets\Api\Controller;

use Cengizkemec\SupportTickets\Webhook\WebhookSender;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Lets an administrator verify the webhook configuration from the extension's
 * settings page. The request may carry the current (unsaved) URL textarea and
 * format so a URL can be tested before saving.
 */
class TestWebhookController implements RequestHandlerInterface
{
    public function __construct(protected WebhookSender $webhooks)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $body = $request->getParsedBody();

        if (is_string($body)) {
            $decoded = json_decode($body, true);
            $body = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($body)) {
            $body = [];
        }

        $urls = isset($body['urls']) && is_string($body['urls']) ? $body['urls'] : null;
        $format = isset($body['format']) && is_string($body['format']) ? $body['format'] : null;

        $results = $this->webhooks->sendTest($urls, $format);
        $success = $results !== [] && ! in_array(false, array_column($results, 'ok'), true);

        return new JsonResponse([
            'success' => $success,
            'results' => $results,
        ]);
    }
}
