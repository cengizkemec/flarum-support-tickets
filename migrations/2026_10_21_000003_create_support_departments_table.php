<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('support_departments', function (Blueprint $table) {
    $table->increments('id');
    $table->string('name', 100);
    $table->string('slug', 100);
    $table->string('description', 500)->nullable();
    $table->json('user_ids')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
