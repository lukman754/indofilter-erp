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
            if (\Illuminate\Support\Facades\DB::connection($this->getConnection())->getDriverName() === 'sqlite') {
                $table->unsignedBigInteger('reference_id')->nullable();
            } else {
                $table->foreignId('reference_id')->nullable()->constrained('documents')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            if (\Illuminate\Support\Facades\DB::connection($this->getConnection())->getDriverName() !== 'sqlite') {
                $table->dropForeign(['reference_id']);
            }
            $table->dropColumn('reference_id');
        });
    }
};
