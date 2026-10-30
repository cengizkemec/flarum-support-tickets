<?php

namespace Cengizkemec\SupportTickets\Api\Resource;

use Cengizkemec\SupportTickets\Model\TicketFormField;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\Foundation\ValidationException;
use Tobyz\JsonApiServer\Context;

class TicketFormFieldResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'ticket-form-fields';
    }

    public function model(): string
    {
        return TicketFormField::class;
    }

    public function scope(object $query, Context $context): void
    {
        $actor = $context->getActor();

        if (! $actor->can('support.manage')) {
            $query->where('is_active', true);
        }
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()->authenticated()->paginate(50, 200)->defaultSort('position'),
            Endpoint\Show::make()->authenticated(),
            Endpoint\Create::make()->authenticated()->admin(),
            Endpoint\Update::make()->authenticated()->admin(),
            Endpoint\Delete::make()->authenticated()->admin(),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('position'),
            SortColumn::make('createdAt'),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('label')->requiredOnCreate()->writable()->maxLength(255),
            Schema\Str::make('name')
                ->writable()
                ->nullable()
                ->maxLength(100)
                ->unique('ticket_form_fields', 'name', true),
            Schema\Str::make('type')->requiredOnCreate()->writable()->in(TicketFormField::TYPES),
            Schema\Arr::make('options')->writable()->nullable()->rule('max:200'),
            Schema\Boolean::make('isRequired')->writable(),
            Schema\Boolean::make('isActive')->writable(),
            Schema\Integer::make('position')->writable()->rule('min:0'),
            Schema\DateTime::make('createdAt'),
            Schema\DateTime::make('updatedAt'),

            Schema\Relationship\ToOne::make('category')->type('ticket-categories')->includable()->writable(),
        ];
    }

    public function creating(object $model, Context $context): ?object
    {
        /** @var TicketFormField $model */
        $this->normalize($model, true);

        return parent::creating($model, $context);
    }

    public function updating(object $model, Context $context): ?object
    {
        /** @var TicketFormField $model */
        $this->normalize($model, false);

        return parent::updating($model, $context);
    }

    /**
     * Normalise the machine name and the choice list before the model is saved.
     * Validation of the raw request happens before this hook, but the name may
     * be generated from the label and options only make sense for choice types.
     */
    protected function normalize(TicketFormField $model, bool $creating): void
    {
        if ($creating) {
            $name = trim((string) ($model->name ?? ''));
            $model->name = TicketFormField::nameFor($name !== '' ? $name : (string) ($model->label ?? ''), $model->id);
        } else {
            $original = (string) $model->getOriginal('name');
            $submitted = trim((string) ($model->name ?? ''));

            // Only rewrite the key when the administrator actually changed it.
            // Keeping the stored key untouched protects answers already saved in
            // tickets.custom_fields (including legacy mixed-case keys).
            if (! $model->isDirty('name') || $submitted === '') {
                $model->name = $original !== ''
                    ? $original
                    : TicketFormField::nameFor((string) ($model->label ?? ''), $model->id);
            } else {
                $model->name = TicketFormField::nameFor($submitted, $model->id);
            }
        }

        $this->assertNameIsUnique($model);

        $rawOptions = $model->options;

        if ($rawOptions !== null && ! is_array($rawOptions)) {
            throw new ValidationException([
                'options' => 'Options must be a list of text values.',
            ]);
        }

        foreach ((array) $rawOptions as $option) {
            if (! is_scalar($option)) {
                throw new ValidationException([
                    'options' => 'Each option must be a text value.',
                ]);
            }

            if (mb_strlen(trim((string) $option)) > 255) {
                throw new ValidationException([
                    'options' => 'Each option must be 255 characters or fewer.',
                ]);
            }
        }

        $options = $model->optionsList();

        if (in_array($model->type, TicketFormField::CHOICE_TYPES, true)) {
            if ($options === []) {
                throw new ValidationException([
                    'options' => 'Select, radio and checkbox fields need at least one option.',
                ]);
            }
        } else {
            // A text field must not carry a stale choice list if its type was
            // changed from select/radio/checkbox.
            $options = [];
        }

        $model->options = $options === [] ? null : $options;

        if ($creating && $model->position === null) {
            $model->position = ((int) TicketFormField::query()->max('position')) + 1;
        }
    }

    protected function assertNameIsUnique(TicketFormField $model): void
    {
        $query = TicketFormField::query()
            ->whereRaw('LOWER(name) = ?', [strtolower((string) $model->name)]);

        if ($model->exists) {
            $query->where('id', '!=', $model->getKey());
        }

        if ($query->exists()) {
            throw new ValidationException([
                'name' => 'This key is already used by another field. Choose a different key.',
            ]);
        }
    }
}
