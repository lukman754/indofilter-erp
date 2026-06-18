<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('company_bank_accounts', 'is_default')) {
            Schema::table('company_bank_accounts', function (Blueprint $table) {
                $table->boolean('is_default')->default(false);
            });
        }

        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'bank_account_id')) {
                $table->foreignId('bank_account_id')->nullable()->constrained('company_bank_accounts')->nullOnDelete();
            }
            if (!Schema::hasColumn('documents', 'is_ppn')) {
                $table->boolean('is_ppn')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropColumn(['bank_account_id', 'is_ppn']);
        });

        Schema::table('company_bank_accounts', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });
    }
};
