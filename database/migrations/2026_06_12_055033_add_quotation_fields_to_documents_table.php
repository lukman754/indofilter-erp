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
            $table->text('stock_conditions')->nullable();
            $table->text('term_of_payment')->nullable();
            $table->text('price_conditions')->nullable();
            $table->text('standard_packing')->nullable();
            $table->text('offer_validity')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['stock_conditions', 'term_of_payment', 'price_conditions', 'standard_packing', 'offer_validity']);
        });
    }
};
