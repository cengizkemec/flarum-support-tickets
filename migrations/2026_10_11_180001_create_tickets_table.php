<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('tickets', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('user_id');
    $table->unsignedInteger('category_id')->nullable();
    $table->unsignedInteger('assigned_to')->nullable();
    $table->string('subject');
    $table->text('message');
    $table->string('status')->default('new');
    $table->string('priority')->default('medium');
    $table->string('attachment')->nullable();
    $table->timestamp('closed_at')->nullable();
    $table->timestamps();

    $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
    $table->foreign('category_id')->references('id')->on('ticket_categories')->nullOnDelete();
    $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
    $table->index(['user_id', 'status']);
    $table->index('status');
});
