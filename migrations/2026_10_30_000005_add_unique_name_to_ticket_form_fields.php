<?php

use Cengizkemec\SupportTickets\Model\TicketFormField;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('ticket_form_fields')) {
            return;
        }

        $db = $schema->getConnection();

        // Older releases did not enforce unique keys. Normalise blank/legacy
        // names and give duplicates a numeric suffix before adding the index,
        // so upgrading never fails or discards an existing field.
        $fields = $db->table('ticket_form_fields')
            ->orderBy('id')
            ->get(['id', 'name', 'label']);

        $used = [];

        // Older releases did not enable timestamps on this model either.
        $now = date('Y-m-d H:i:s');
        $db->table('ticket_form_fields')->whereNull('created_at')->update(['created_at' => $now]);
        $db->table('ticket_form_fields')->whereNull('updated_at')->update(['updated_at' => $now]);

        foreach ($fields as $field) {
            $id = (int) $field->id;
            $current = trim((string) ($field->name ?? ''));

            // Never rewrite an already valid key: tickets may have stored
            // answers under it. Only blank/legacy names are normalised.
            $base = preg_match('/^[A-Za-z0-9][A-Za-z0-9_]{0,99}$/', $current)
                ? $current
                : TicketFormField::nameFor($current !== '' ? $current : (string) ($field->label ?? ''), $id);

            $name = $base;
            $suffix = 2;

            while (
                isset($used[strtolower($name)])
                || $db->table('ticket_form_fields')
                    ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                    ->where('id', '!=', $id)
                    ->exists()
            ) {
                $suffixText = '_'.$suffix++;
                $name = substr($base, 0, 100 - strlen($suffixText)).$suffixText;
            }

            if ($name !== (string) $field->name) {
                $db->table('ticket_form_fields')->where('id', $id)->update(['name' => $name]);
            }

            $used[strtolower($name)] = true;
        }

        $schema->table('ticket_form_fields', function (Blueprint $table) {
            $table->unique('name');
        });
    },

    'down' => function (Builder $schema) {
        if (! $schema->hasTable('ticket_form_fields')) {
            return;
        }

        $schema->table('ticket_form_fields', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    },
];
