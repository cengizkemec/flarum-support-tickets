<?php

namespace Cengizkemec\SupportTickets\Webhook;

use Carbon\Carbon;
use Cengizkemec\SupportTickets\Model\Ticket;
use Cengizkemec\SupportTickets\Model\TicketReply;
use Cengizkemec\SupportTickets\Support\TicketCustomFields;
use Cengizkemec\SupportTickets\Support\TicketSlug;
use Flarum\Http\UrlGenerator;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Delivers support-ticket notifications to Discord, Slack and any other
 * webhook endpoint an administrator configured.
 *
 * Delivery is best effort: a broken endpoint must never stop a ticket from
 * being created or a reply from being posted. Failures are written to the
 * Flarum log and, for the admin "send test" button, returned to the caller.
 */
class WebhookSender
{
    private const MAX_ENDPOINTS = 10;
    private const MAX_CUSTOM_FIELDS = 6;
    private const MAX_RETRY_SECONDS = 3.0;

    /**
     * Human-friendly prefixes accepted in the URL textarea. "json" and
     * "webhook" are aliases for the generic format; Mattermost and
     * Rocket.Chat understand Slack's incoming-webhook payload.
     */
    private const FORMAT_ALIASES = [
        'json' => WebhookPayload::FORMAT_GENERIC,
        'webhook' => WebhookPayload::FORMAT_GENERIC,
        'mattermost' => WebhookPayload::FORMAT_SLACK,
        'rocket' => WebhookPayload::FORMAT_SLACK,
        'rocketchat' => WebhookPayload::FORMAT_SLACK,
    ];

    protected ClientInterface $client;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected UrlGenerator $urls,
        protected TranslatorInterface $translator,
        protected LoggerInterface $logger
    ) {
        $this->client = new Client([
            'timeout' => 8,
            'connect_timeout' => 5,
            'http_errors' => false,
            // A webhook endpoint should answer directly. Following redirects
            // would let a misconfigured URL turn into an arbitrary outbound
            // request (and could leak ticket content somewhere unintended).
            'allow_redirects' => false,
        ]);
    }

    public function ticketCreated(Ticket $ticket): void
    {
        if (! $this->eventEnabled('webhook_new_ticket')) {
            return;
        }

        try {
            $this->deliver($this->ticketCreatedMessage($ticket));
        } catch (Throwable $e) {
            $this->logger->error('Failed to build the new-ticket webhook: ' . $e->getMessage(), [
                'ticket_id' => $ticket->id,
            ]);
        }
    }

    public function ticketReplied(TicketReply $reply): void
    {
        if (! $this->eventEnabled('webhook_ticket_reply')) {
            return;
        }

        try {
            $message = $this->ticketRepliedMessage($reply);

            if ($message !== null) {
                $this->deliver($message);
            }
        } catch (Throwable $e) {
            $this->logger->error('Failed to build the ticket-reply webhook: ' . $e->getMessage(), [
                'reply_id' => $reply->id,
            ]);
        }
    }

    /**
     * Send a test message. Unlike the event methods this ignores the master
     * switch and event toggles, so an administrator can verify a URL before
     * saving the form.
     *
     * @return array<int, array{endpoint: string, format: string, ok: bool, status: int, error: string}>
     */
    public function sendTest(?string $urlsText = null, ?string $format = null): array
    {
        $endpoints = $this->endpoints($urlsText, $format);

        if ($endpoints === []) {
            return [];
        }

        $forum = $this->forumTitle();
        $timestamp = Carbon::now()->toIso8601String();

        $message = $this->message(
            event: 'webhook.test',
            title: $this->trans('cengizkemec-support.webhook.test_title'),
            url: $this->baseUrl(),
            description: $this->trans('cengizkemec-support.webhook.test_description', ['forum' => $forum]),
            fields: [],
            color: 0x6b7280,
            timestamp: $timestamp,
            data: [
                'test' => true,
                'forum' => $forum,
                'timestamp' => $timestamp,
            ]
        );

        return $this->deliver($message, $endpoints);
    }

    /**
     * Parse the "one URL per line" setting. A line may start with
     * "format|" to override the default format for that endpoint.
     *
     * @return array<int, array{url: string, format: string, valid: bool}>
     */
    public function endpoints(?string $urlsText = null, ?string $format = null): array
    {
        $raw = $urlsText ?? (string) $this->settings->get('cengizkemec-support.webhook_urls', '');
        $default = $this->normaliseFormat($format ?? (string) $this->settings->get('cengizkemec-support.webhook_format', WebhookPayload::FORMAT_DISCORD));

        $endpoints = [];
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $endpointFormat = $default;

            if (str_contains($line, '|')) {
                [$prefix, $rest] = explode('|', $line, 2);
                $prefixFormat = $this->formatFromPrefix($prefix);

                if ($prefixFormat !== null) {
                    $endpointFormat = $prefixFormat;
                    $line = trim($rest);
                }
            }

            $endpoints[] = [
                'url' => $line,
                'format' => $endpointFormat,
                'valid' => $this->validUrl($line),
            ];

            if (count($endpoints) >= self::MAX_ENDPOINTS) {
                break;
            }
        }

        return $endpoints;
    }

    /**
     * @param array<string, mixed> $message
     * @param array<int, array{url: string, format: string, valid: bool}>|null $endpoints
     * @return array<int, array{endpoint: string, format: string, ok: bool, status: int, error: string}>
     */
    protected function deliver(array $message, ?array $endpoints = null): array
    {
        $endpoints ??= $this->endpoints();

        if ($endpoints === []) {
            return [];
        }

        $results = [];

        foreach ($endpoints as $endpoint) {
            $payload = WebhookPayload::format($endpoint['format'], $message);
            $results[] = $this->send($endpoint, $payload, (string) $message['event']);
        }

        return $results;
    }

    /**
     * @param array{url: string, format: string, valid: bool} $endpoint
     * @param array<string, mixed> $payload
     * @return array{endpoint: string, format: string, ok: bool, status: int, error: string}
     */
    protected function send(array $endpoint, array $payload, string $event): array
    {
        $display = $this->maskUrl($endpoint['url']);

        if (! $endpoint['valid']) {
            $this->logger->warning('Support ticket webhook URL is invalid.', [
                'endpoint' => $display,
            ]);

            return [
                'endpoint' => $display,
                'format' => $endpoint['format'],
                'ok' => false,
                'status' => 0,
                'error' => 'invalid_url',
            ];
        }

        $body = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if (! is_string($body)) {
            return [
                'endpoint' => $display,
                'format' => $endpoint['format'],
                'ok' => false,
                'status' => 0,
                'error' => 'json_encode_failed',
            ];
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'Flarum-SupportTickets',
            'X-Support-Ticket-Event' => $event,
        ];

        $attempt = 0;

        while (true) {
            try {
                $response = $this->client->request('POST', $endpoint['url'], [
                    'body' => $body,
                    'headers' => $headers,
                    'http_errors' => false,
                ]);
            } catch (Throwable $e) {
                $error = $this->sanitiseError($e->getMessage(), $endpoint['url'], $display);

                $this->logger->warning('Support ticket webhook request failed.', [
                    'endpoint' => $display,
                    'error' => $error,
                ]);

                return [
                    'endpoint' => $display,
                    'format' => $endpoint['format'],
                    'ok' => false,
                    'status' => 0,
                    'error' => $error,
                ];
            }

            $status = $response->getStatusCode();

            if ($status >= 200 && $status < 300) {
                return [
                    'endpoint' => $display,
                    'format' => $endpoint['format'],
                    'ok' => true,
                    'status' => $status,
                    'error' => '',
                ];
            }

            $responseBody = (string) $response->getBody();

            // Discord (and some compatible servers) answer with 429 and a
            // retry_after value when a channel is busy. Retry briefly instead
            // of losing the notification entirely.
            if ($status === 429 && $attempt < 2) {
                $retryAfter = $this->retryAfter($response, $responseBody);

                if ($retryAfter > 0 && $retryAfter <= self::MAX_RETRY_SECONDS) {
                    $attempt++;
                    usleep((int) round($retryAfter * 1_000_000));
                    continue;
                }
            }

            $error = $this->sanitiseError($responseBody, $endpoint['url'], $display);

            if ($error === '') {
                $error = 'HTTP ' . $status;
            }

            $this->logger->warning('Support ticket webhook was rejected.', [
                'endpoint' => $display,
                'status' => $status,
                'response' => $error,
            ]);

            return [
                'endpoint' => $display,
                'format' => $endpoint['format'],
                'ok' => false,
                'status' => $status,
                'error' => $error,
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function ticketCreatedMessage(Ticket $ticket): array
    {
        $ticket->loadMissing(['user', 'category.department', 'assignee']);

        $url = $this->ticketUrl($ticket);
        $customFields = $this->customFields($ticket);
        $category = $ticket->category;

        $fields = [
            $this->field($this->trans('cengizkemec-support.webhook.fields.category'), $category?->name),
            $this->field($this->trans('cengizkemec-support.webhook.fields.priority'), $this->priorityLabel($ticket->priority)),
            $this->field($this->trans('cengizkemec-support.webhook.fields.status'), $this->statusLabel($ticket->status)),
            $this->field($this->trans('cengizkemec-support.webhook.fields.opened_by'), $this->displayName($ticket->user)),
            $this->field($this->trans('cengizkemec-support.webhook.fields.department'), $category?->department?->name),
            $this->field($this->trans('cengizkemec-support.webhook.fields.assigned_to'), $this->displayName($ticket->assignee)),
        ];

        foreach (array_slice($customFields, 0, self::MAX_CUSTOM_FIELDS) as $customField) {
            $fields[] = $this->field($customField['label'], $customField['value'], false);
        }

        $data = [
            'ticket' => $this->ticketData($ticket, true),
            'user' => $this->userData($ticket->user),
        ];

        return $this->message(
            event: 'ticket.created',
            title: $this->trans('cengizkemec-support.webhook.new_ticket_title', [
                'subject' => (string) $ticket->subject,
            ]),
            url: $url,
            description: $this->content((string) $ticket->message),
            fields: $fields,
            color: 0x1d4ed8,
            timestamp: $this->iso($ticket->created_at),
            data: $data
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function ticketRepliedMessage(TicketReply $reply): ?array
    {
        $ticket = $reply->ticket;

        if (! $ticket) {
            return null;
        }

        $ticket->loadMissing(['user', 'category.department', 'assignee']);
        $reply->loadMissing('user');

        $isStaff = (bool) $reply->is_staff;
        $url = $this->ticketUrl($ticket);
        $category = $ticket->category;

        $fields = [
            $this->field($this->trans('cengizkemec-support.webhook.fields.category'), $category?->name),
            $this->field($this->trans('cengizkemec-support.webhook.fields.priority'), $this->priorityLabel($ticket->priority)),
            $this->field($this->trans('cengizkemec-support.webhook.fields.status'), $this->statusLabel($ticket->status)),
            $this->field($this->trans('cengizkemec-support.webhook.fields.replied_by'), $this->displayName($reply->user)),
            $this->field(
                $this->trans('cengizkemec-support.webhook.fields.staff_reply'),
                $isStaff
                    ? $this->trans('cengizkemec-support.webhook.fields.yes')
                    : $this->trans('cengizkemec-support.webhook.fields.no')
            ),
            $this->field($this->trans('cengizkemec-support.webhook.fields.assigned_to'), $this->displayName($ticket->assignee)),
        ];

        $data = [
            'ticket' => $this->ticketData($ticket, false),
            'reply' => [
                'id' => (int) $reply->id,
                'message' => $this->content((string) $reply->message),
                'is_staff' => $isStaff,
                'author' => $this->userData($reply->user),
                'created_at' => $this->iso($reply->created_at),
                'url' => $url,
            ],
        ];

        return $this->message(
            event: 'ticket.replied',
            title: $this->trans(
                $isStaff
                    ? 'cengizkemec-support.webhook.staff_reply_title'
                    : 'cengizkemec-support.webhook.reply_title',
                ['subject' => (string) $ticket->subject]
            ),
            url: $url,
            description: $this->content((string) $reply->message),
            fields: $fields,
            color: $isStaff ? 0x8b5cf6 : 0x10b981,
            timestamp: $this->iso($reply->created_at),
            data: $data
        );
    }

    /**
     * @param array<int, array{name: string, value: string, inline?: bool}> $fields
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function message(
        string $event,
        string $title,
        string $url,
        string $description,
        array $fields,
        int $color,
        string $timestamp,
        array $data
    ): array {
        return [
            'event' => $event,
            'title' => trim(preg_replace('/\s+/u', ' ', $title) ?? $title),
            'url' => $url,
            'link_label' => $this->trans('cengizkemec-support.webhook.view_ticket'),
            'description' => $description,
            'fields' => $fields,
            'color' => $color,
            'timestamp' => $timestamp,
            'footer' => $this->forumTitle(),
            'username' => $this->username(),
            'data' => $data,
        ];
    }

    /**
     * @return array{name: string, value: string, inline: bool}
     */
    protected function field(string $name, ?string $value, bool $inline = true): array
    {
        $value = trim((string) $value);

        return [
            'name' => $name,
            'value' => $value === ''
                ? $this->trans('cengizkemec-support.webhook.fields.none')
                : $value,
            'inline' => $inline,
        ];
    }

    /**
     * @return array<int, array{label: string, value: string, type: string}>
     */
    protected function customFields(Ticket $ticket): array
    {
        try {
            $fields = TicketCustomFields::present(
                (array) $ticket->custom_fields,
                $ticket->category_id ? (int) $ticket->category_id : null
            );

            return array_map(fn (array $field) => [
                'label' => (string) ($field['label'] ?? ''),
                'value' => (string) ($field['value'] ?? ''),
                'type' => (string) ($field['type'] ?? 'text'),
            ], $fields);
        } catch (Throwable $e) {
            // Custom fields are a nice extra, never a reason to drop the
            // notification.
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function ticketData(Ticket $ticket, bool $includeMessage): array
    {
        $category = $ticket->category;

        return [
            'id' => (int) $ticket->id,
            'subject' => (string) $ticket->subject,
            'message' => $includeMessage ? $this->content((string) $ticket->message) : null,
            'status' => (string) $ticket->status,
            'priority' => (string) $ticket->priority,
            'category' => $category?->name,
            'department' => $category?->department?->name,
            'assignee' => $this->displayName($ticket->assignee),
            'custom_fields' => $this->customFields($ticket),
            'url' => $this->ticketUrl($ticket),
            'created_at' => $this->iso($ticket->created_at),
        ];
    }

    /**
     * @return array{id: int|null, username: string|null, display_name: string|null}|null
     */
    protected function userData(?User $user): ?array
    {
        if (! $user) {
            return null;
        }

        return [
            'id' => (int) $user->id,
            'username' => $user->username,
            'display_name' => $user->display_name,
        ];
    }

    protected function displayName(?User $user): string
    {
        if (! $user) {
            return $this->trans('cengizkemec-support.webhook.fields.none');
        }

        return (string) ($user->display_name ?: $user->username);
    }

    protected function statusLabel(?string $status): string
    {
        return $this->translatedFallback('cengizkemec-support.forum.status.', (string) $status);
    }

    protected function priorityLabel(?string $priority): string
    {
        return $this->translatedFallback('cengizkemec-support.forum.priority.', (string) $priority);
    }

    protected function translatedFallback(string $prefix, string $value): string
    {
        if ($value === '') {
            return $this->trans('cengizkemec-support.webhook.fields.none');
        }

        $key = $prefix . $value;
        $label = (string) $this->translator->trans($key);

        return $label === $key ? ucfirst($value) : $label;
    }

    protected function content(string $message): string
    {
        if (! (bool) $this->settings->get('cengizkemec-support.webhook_include_content', true)) {
            return '';
        }

        $text = trim(strip_tags($message));

        if (mb_strlen($text) > 1200) {
            $text = rtrim(mb_substr($text, 0, 1199)) . '…';
        }

        return $text;
    }

    protected function eventEnabled(string $setting): bool
    {
        if (! (bool) $this->settings->get('cengizkemec-support.webhook_enabled', false)) {
            return false;
        }

        return (bool) $this->settings->get('cengizkemec-support.' . $setting, true);
    }

    protected function ticketUrl(Ticket $ticket): string
    {
        $path = TicketSlug::path((int) $ticket->id, $ticket->subject);

        return $this->baseUrl() . $path;
    }

    protected function baseUrl(): string
    {
        try {
            return rtrim($this->urls->to('forum')->base(), '/');
        } catch (Throwable $e) {
            return '';
        }
    }

    protected function forumTitle(): string
    {
        $title = trim((string) $this->settings->get('forum_title', ''));

        return $title !== '' ? $title : 'Support Tickets';
    }

    protected function username(): string
    {
        $username = trim((string) $this->settings->get('cengizkemec-support.webhook_username', ''));

        return $username !== '' ? $username : $this->forumTitle();
    }

    protected function iso(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return Carbon::instance($date)->toIso8601String();
        }

        return Carbon::now()->toIso8601String();
    }

    protected function normaliseFormat(string $format): string
    {
        return $this->formatFromPrefix($format) ?? WebhookPayload::FORMAT_DISCORD;
    }

    protected function formatFromPrefix(string $prefix): ?string
    {
        $prefix = strtolower(trim($prefix));
        $prefix = self::FORMAT_ALIASES[$prefix] ?? $prefix;

        return in_array($prefix, WebhookPayload::FORMATS, true) ? $prefix : null;
    }

    protected function validUrl(string $url): bool
    {
        if ($url === '' || preg_match('/\s/', $url)) {
            return false;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);

        return in_array($scheme, ['http', 'https'], true) && $host !== '';
    }

    /**
     * Strip the secret part of a webhook URL before it reaches the log or the
     * admin response. Discord tokens live in the last path segment.
     */
    protected function maskUrl(string $url): string
    {
        $scheme = (string) parse_url($url, PHP_URL_SCHEME);
        $host = (string) parse_url($url, PHP_URL_HOST);

        if ($host === '') {
            return 'invalid-url';
        }

        return ($scheme !== '' ? $scheme : 'https') . '://' . $host;
    }

    protected function retryAfter(ResponseInterface $response, string $body): float
    {
        $header = trim($response->getHeaderLine('Retry-After'));
        $decoded = json_decode($body, true);

        if (is_array($decoded) && isset($decoded['retry_after']) && is_numeric($decoded['retry_after'])) {
            return (float) $decoded['retry_after'];
        }

        return is_numeric($header) ? (float) $header : 0.0;
    }

    protected function shortError(string $value): string
    {
        $value = trim(strip_tags($value));

        if (mb_strlen($value) > 300) {
            $value = rtrim(mb_substr($value, 0, 299)) . '…';
        }

        return $value;
    }

    /**
     * Guzzle exception messages include the full URI, which may contain a
     * Discord token. Replace it with the masked form before it is logged or
     * shown in the admin panel.
     */
    protected function sanitiseError(string $message, string $url, string $masked): string
    {
        $message = str_replace([$url, rawurldecode($url)], $masked, $message);

        return $this->shortError($message);
    }

    /**
     * Flarum's translations use {name} placeholders (the format the frontend
     * translator expects), while Symfony's PHP translator replaces the raw
     * key. Supplying all three shapes keeps both sides working.
     *
     * @param array<string, mixed> $parameters
     */
    protected function trans(string $key, array $parameters = []): string
    {
        $replacements = [];

        foreach ($parameters as $name => $value) {
            $replacements[$name] = $value;
            $replacements['{' . $name . '}'] = $value;
            $replacements['%' . $name . '%'] = $value;
        }

        return (string) $this->translator->trans($key, $replacements);
    }
}
