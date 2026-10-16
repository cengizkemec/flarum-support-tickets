<?php

use Flarum\Database\Migration;

return Migration::addColumns('tickets', [
    'custom_fields' => ['text', 'nullable' => true],
]);
