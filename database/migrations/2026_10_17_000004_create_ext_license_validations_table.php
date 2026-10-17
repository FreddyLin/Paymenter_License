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
        Schema::create('ext_license_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('ext_licenses')->cascadeOnDelete();
            $table->boolean('valid');
            $table->string('ip')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['license_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ext_license_validations');
    }
};
