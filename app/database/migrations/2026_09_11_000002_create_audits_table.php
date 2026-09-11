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
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->onDelete('cascade');
            $table->unsignedSmallInteger('score')->default(100);
            $table->string('risk_level', 20)->default('LOW'); // LOW, MODERATE, HIGH, CRITICAL
            $table->decimal('cpu_usage', 5, 2)->nullable();
            $table->decimal('memory_usage', 5, 2)->nullable();
            $table->decimal('disk_usage', 5, 2)->nullable();
            $table->unsignedInteger('failed_services_count')->default(0);
            $table->string('firewall_status', 30)->nullable();
            $table->string('ssh_port', 10)->nullable();
            $table->json('raw_payload')->nullable();
            $table->string('status', 30)->default('completed'); // pending, completed, failed
            $table->timestamp('audited_at')->useCurrent();
            $table->timestamps();

            $table->index(['server_id', 'audited_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
