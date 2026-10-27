<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('module')->unique();
            $table->string('template_name')->nullable();
            $table->string('language', 16)->default('en_US');
            $table->string('parameter_format', 24)->default('positional');
            $table->json('variables')->nullable();
            $table->string('status', 24)->default('draft');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_templates');
    }
};
