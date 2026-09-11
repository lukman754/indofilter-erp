<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Kita drop index unique lama di SQLite/MySQL
            // Di SQLite, dropUnique membutuhkan nama index atau drop index manual.
            // Namun, cara teraman di Laravel lintas-DB adalah drop index lama dan pasang index komposit [type, document_number].
            try {
                $table->dropUnique(['document_number']);
            } catch (\Exception $e) {
                // Di beberapa versi SQLite, index dinamakan 'documents_document_number_unique'
                try {
                    $table->dropUnique('documents_document_number_unique');
                } catch (\Exception $ex) {
                    // Abaikan jika index tidak ditemukan atau SQLite tidak mendukung drop index langsung
                }
            }

            // Buat unique komposit agar kombinasi type + document_number yang unik (sehingga PI dan INV nomor depannya sama tidak error)
            $table->unique(['type', 'document_number']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            try {
                $table->dropUnique(['type', 'document_number']);
            } catch (\Exception $e) {}

            $table->string('document_number')->nullable()->unique()->change();
        });
    }
};
