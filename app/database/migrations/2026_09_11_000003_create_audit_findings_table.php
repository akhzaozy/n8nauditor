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
        Schema::create('audit_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained('audits')->onDelete('cascade');
            $table->string('finding_code', 30)->index();
            $table->string('category', 50)->index();
            $table->string('severity', 20)->index(); // LOW, MODERATE, HIGH, CRITICAL
            $table->integer('score_impact')->default(0);
            $table->string('title');
            $table->text('evidence')->nullable();
            $table->text('description')->nullable();
            $table->text('recommendation')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_findings');
    }
};
