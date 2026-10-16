<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('ticket_form_fields', function (Blueprint $table) {
    $table->increments('id');
    // When set, the field is only shown for tickets in that category.
    // When null the field is shown for every ticket (a "global" field).
    $table->unsignedInteger('category_id')->nullable();
    $table->string('label');
    // Machine name used as the key inside tickets.custom_fields.
    $table->string('name');
    $table->string('type')->default('text');
    // JSON encoded list of choices for select/radio/checkbox fields.
    $table->text('options')->nullable();
    $table->boolean('is_required')->default(false);
    $table->boolean('is_active')->default(true);
    $table->integer('position')->default(0);
    $table->timestamps();

    $table->foreign('category_id')->references('id')->on('ticket_categories')->nullOnDelete();
    $table->index(['is_active', 'position']);
});
