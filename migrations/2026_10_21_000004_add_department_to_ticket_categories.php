<?php
use Flarum\Database\Migration;

return Migration::addColumns('ticket_categories', [
    'department_id' => ['integer', 'nullable' => true],
]);
