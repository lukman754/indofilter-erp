<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->string('pic')->nullable()->after('description'); // orang yang menggunakan kas (opsional)
            $table->boolean('is_marked')->default(false)->after('pic'); // penanda checkbox
        });
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropColumn(['pic', 'is_marked']);
        });
    }
};
