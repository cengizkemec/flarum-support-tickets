<?php

namespace Cengizkemec\SupportTickets\Api\Resource;

use Cengizkemec\SupportTickets\Model\SupportDepartment;
use Tobyz\JsonApiServer\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;

class SupportDepartmentResource extends AbstractDatabaseResource
{
    public function type(): string { return 'support-departments'; }
    public function model(): string { return SupportDepartment::class; }
    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()->authenticated(),
            Endpoint\Show::make()->authenticated(),
            Endpoint\Create::make()->authenticated()->admin(),
            Endpoint\Update::make()->authenticated()->admin(),
            Endpoint\Delete::make()->authenticated()->admin(),
        ];
    }
    public function fields(): array
    {
        return [
            Schema\Str::make('name')->requiredOnCreate()->writable(),
            Schema\Str::make('slug')->writable(),
            Schema\Str::make('description')->writable()->nullable(),
            Schema\Arr::make('userIds')->writable()->nullable(),
            Schema\Boolean::make('isActive')->writable(),
        ];
    }
}
