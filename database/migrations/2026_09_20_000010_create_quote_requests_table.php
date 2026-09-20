<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->foreignId('province_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->text('message')->nullable();
            $table->json('photos')->nullable();
            $table->date('preferred_date')->nullable();
            $table->boolean('kvkk_accepted')->default(false);
            $table->string('status', 30)->default('new')->index();
            $table->text('admin_notes')->nullable();
            $table->string('source', 30)->default('form');
            $table->string('page_url')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
