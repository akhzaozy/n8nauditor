<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('hostname')->index();
            $table->string('ip_address')->nullable();
            $table->string('os_info')->nullable();
            $table->text('description')->nullable();
            $table->string('api_token', 64)->unique()->nullable();
            $table->string('status', 20)->default('active'); // active, inactive, maintenance
            $table->timestamp('last_audited_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};
