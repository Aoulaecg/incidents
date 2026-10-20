<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parkings', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });
        Schema::create('parking_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('parking_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'parking_id']);
        });
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('parking_id')->constrained()->restrictOnDelete();
            $table->date('date_incident');
            $table->time('heure_incident')->nullable();
            $table->text('description')->nullable();
            $table->enum('type_declaration', ['incident', 'rien_a_signaler']);
            $table->enum('status', ['active', 'superseded'])->default('active');
            $table->dateTime('validated_at')->nullable();
            $table->dateTime('superseded_at')->nullable();
            $table->foreignId('superseded_by_id')->nullable()->constrained('incidents')->restrictOnDelete();
            $table->uuid('submission_token');
            $table->string('ras_key', 64)->nullable()->unique();
            $table->timestamps();
            $table->unique(['user_id', 'submission_token']);
            $table->index(['parking_id', 'date_incident', 'status']);
            $table->index(['date_incident', 'type_declaration']);
            $table->index(['parking_id', 'created_at']);
        });
        Schema::create('incident_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('event', 32);
            $table->json('details');
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_audits');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('parking_user');
        Schema::dropIfExists('parkings');
    }
};
