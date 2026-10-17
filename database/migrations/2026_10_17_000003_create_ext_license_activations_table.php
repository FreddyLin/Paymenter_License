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
        Schema::create('ext_license_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('ext_licenses')->cascadeOnDelete();
            $table->string('domain')->nullable();
            $table->string('ip')->nullable();
            $table->timestamps();

            $table->unique(['license_id', 'domain']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ext_license_activations');
    }
};
