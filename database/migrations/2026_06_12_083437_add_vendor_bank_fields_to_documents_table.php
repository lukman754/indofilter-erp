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
        Schema::table('documents', function (Blueprint $table) {
            $table->string('vendor_bank_name')->nullable();
            $table->string('vendor_bank_account_name')->nullable();
            $table->string('vendor_bank_account_number')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['vendor_bank_name', 'vendor_bank_account_name', 'vendor_bank_account_number']);
        });
    }
};
