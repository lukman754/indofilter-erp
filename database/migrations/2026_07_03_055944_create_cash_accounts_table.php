<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // e.g. "Kas Umum", "Kas Parkir", "Kasbon"
            $table->text('description')->nullable();
            $table->unsignedBigInteger('company_id')->nullable(); // nullable, for future multi-company
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_accounts');
    }
};
