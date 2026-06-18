<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['quotation', 'proforma_invoice', 'invoice', 'delivery_slip', 'delivery_address', 'purchase_order']);
            $table->string('document_number')->nullable()->unique();
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->enum('status', ['draft', 'confirmed', 'canceled'])->default('draft');
            $table->text('terms')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
