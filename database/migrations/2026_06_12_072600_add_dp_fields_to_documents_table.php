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
            if (!Schema::hasColumn('documents', 'dp_percent')) {
                $table->decimal('dp_percent', 5, 2)->nullable();
            }
            if (!Schema::hasColumn('documents', 'dp_amount')) {
                $table->decimal('dp_amount', 15, 2)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['dp_percent', 'dp_amount']);
        });
    }
};
