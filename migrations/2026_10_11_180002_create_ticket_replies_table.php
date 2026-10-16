<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('ticket_replies', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('ticket_id');
    $table->unsignedInteger('user_id');
    $table->text('message');
    $table->string('attachment')->nullable();
    $table->boolean('is_staff')->default(false);
    $table->timestamps();

    $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
});
