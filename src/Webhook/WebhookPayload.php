<?php

namespace Cengizkemec\SupportTickets\Webhook;

/**
 * Turns a normalised ticket message into the payload a webhook endpoint
 * expects.
 *
 * The class deliberately has no Flarum dependencies: the sender builds the
 * message once, then this class renders it in the format each endpoint asked
 * for (Discord embeds, Slack mrkdwn, Microsoft Teams MessageCards or a plain
 * JSON document that any other service can consume).
 */
class WebhookPayload
{
    public const FORMAT_DISCORD = 'discord';
    public const FORMAT_SLACK = 'slack';
    public const FORMAT_TEAMS = 'teams';
    public const FORMAT_GENERIC = 'generic';

    public const FORMATS = [
        self::FORMAT_DISCORD,
        self::FORMAT_SLACK,
        self::FORMAT_TEAMS,
        self::FORMAT_GENERIC,
    ];

    /**
     * @param array{
     *     event: string,
     *     title: string,
     *     url: string,
     *     link_label: string,
     *     description: string,
     *     fields: array<int, array{name: string, value: string, inline?: bool}>,
     *     color: int,
     *     timestamp: string,
     *     footer: string,
     *     username: string,
     *     data: array<string, mixed>
     * } $message
     *
     * @return array<string, mixed>
     */
    public static function format(string $format, array $message): array
    {
        return match ($format) {
            self::FORMAT_SLACK => self::slack($message),
            self::FORMAT_TEAMS => self::teams($message),
            self::FORMAT_GENERIC => self::generic($message),
            default => self::discord($message),
        };
    }

    /**
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    protected static function discord(array $message): array
    {
        $embed = [
            'title' => self::clip((string) $message['title'], 256),
            'color' => (int) $message['color'],
            'timestamp' => (string) $message['timestamp'],
        ];

        if ((string) $message['url'] !== '') {
            $embed['url'] = (string) $message['url'];
        }

        if ((string) $message['description'] !== '') {
            $embed['description'] = self::clip((string) $message['description'], 1200);
        }

        $fields = [];

        foreach (array_slice($message['fields'], 0, 10) as $field) {
            $name = self::clip((string) ($field['name'] ?? ''), 100);
            $value = trim((string) ($field['value'] ?? ''));

            if ($name === '' || $value === '') {
                continue;
            }

            $fields[] = [
                'name' => $name,
                'value' => self::clip($value, 250),
                'inline' => (bool) ($field['inline'] ?? false),
            ];
        }

        if ($fields !== []) {
            $embed['fields'] = $fields;
        }

        if ((string) $message['footer'] !== '') {
            $embed['footer'] = ['text' => self::clip((string) $message['footer'], 128)];
        }

        $payload = [
            'embeds' => [$embed],
            // Ticket subjects and messages are user supplied: never let them
            // mention @everyone, roles or users in the Discord channel.
            'allowed_mentions' => ['parse' => []],
        ];

        $username = trim((string) $message['username']);

        if ($username !== '') {
            $payload['username'] = self::clip($username, 80);
        }

        return $payload;
    }

    /**
     * Slack incoming webhooks. The simple `text` shape also works with
     * Mattermost, Rocket.Chat and other Slack-compatible receivers.
     *
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    protected static function slack(array $message): array
    {
        $lines = ['*' . self::slackEscape((string) $message['title']) . '*'];

        $inline = [];

        foreach ($message['fields'] as $field) {
            $value = trim((string) ($field['value'] ?? ''));

            if ($value === '') {
                continue;
            }

            $line = '*' . self::slackEscape((string) ($field['name'] ?? '')) . ':* '
                . self::slackEscape($value);

            if ((bool) ($field['inline'] ?? false)) {
                $inline[] = $line;
            } else {
                $lines[] = $line;
            }
        }

        if ($inline !== []) {
            array_splice($lines, 1, 0, [implode(' • ', $inline)]);
        }

        if ((string) $message['description'] !== '') {
            $lines[] = '';
            $lines[] = self::clip(self::slackEscape((string) $message['description']), 3000);
        }

        if ((string) $message['url'] !== '') {
            $lines[] = '';
            $lines[] = '<' . (string) $message['url'] . '|'
                . self::slackEscape((string) $message['link_label']) . '>';
        }

        return ['text' => implode("\n", $lines)];
    }

    /**
     * Microsoft Teams incoming webhook (legacy MessageCard format, still the
     * one accepted by Office 365 connectors).
     *
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    protected static function teams(array $message): array
    {
        $facts = [];

        foreach ($message['fields'] as $field) {
            $name = trim((string) ($field['name'] ?? ''));
            $value = trim((string) ($field['value'] ?? ''));

            if ($name === '' || $value === '') {
                continue;
            }

            $facts[] = [
                'name' => self::clip(self::teamsEscape($name), 256),
                'value' => self::clip(self::teamsEscape($value), 1000),
            ];
        }

        $sections = [];

        if ((string) $message['description'] !== '') {
            $sections[] = ['text' => self::clip(self::teamsEscape((string) $message['description']), 3000)];
        }

        if ($facts !== []) {
            $sections[] = ['facts' => $facts];
        }

        $payload = [
            '@type' => 'MessageCard',
            '@context' => 'http://schema.org/extensions',
            'summary' => self::clip(self::teamsEscape((string) $message['title']), 300),
            'themeColor' => sprintf('%06X', (int) $message['color']),
            'title' => self::clip(self::teamsEscape((string) $message['title']), 300),
        ];

        if ($sections !== []) {
            $payload['sections'] = $sections;
        }

        if ((string) $message['url'] !== '') {
            $payload['potentialAction'] = [[
                '@type' => 'OpenUri',
                'name' => self::teamsEscape((string) $message['link_label']),
                'targets' => [[
                    'os' => 'default',
                    'uri' => (string) $message['url'],
                ]],
            ]];
        }

        return $payload;
    }

    /**
     * A stable, documented JSON document for custom integrations.
     *
     * @param array<string, mixed> $message
     * @return array<string, mixed>
     */
    protected static function generic(array $message): array
    {
        $fields = [];

        foreach ($message['fields'] as $field) {
            $fields[] = [
                'name' => (string) ($field['name'] ?? ''),
                'value' => (string) ($field['value'] ?? ''),
                'inline' => (bool) ($field['inline'] ?? false),
            ];
        }

        return [
            'event' => (string) $message['event'],
            'title' => (string) $message['title'],
            'description' => (string) $message['description'],
            'url' => (string) $message['url'],
            'color' => sprintf('#%06x', (int) $message['color']),
            'timestamp' => (string) $message['timestamp'],
            'forum' => (string) $message['footer'],
            'fields' => $fields,
            'data' => $message['data'],
        ];
    }

    protected static function clip(string $value, int $limit): string
    {
        $value = trim($value);

        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, max(0, $limit - 1))) . '…';
    }

    /**
     * Slack treats &, < and > as control characters in message text.
     */
    protected static function slackEscape(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }

    /**
     * Teams renders MessageCard text as Markdown: neutralise HTML tags in
     * user-supplied ticket content instead of letting the client interpret
     * them.
     */
    protected static function teamsEscape(string $value): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $value);
    }
}
