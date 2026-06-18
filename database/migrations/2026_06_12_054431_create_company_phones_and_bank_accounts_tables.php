<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_phones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('phone');
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Schema::create('company_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number');
            $table->timestamps();
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'bank_account_name', 'bank_account_number']);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
        });

        Schema::dropIfExists('company_bank_accounts');
        Schema::dropIfExists('company_phones');
    }
};
