<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->date('last_invoice_date')->nullable()->after('additional_names');
            $table->string('last_invoice_code', 100)->nullable()->after('last_invoice_date');
        });
    }

    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn(['last_invoice_date', 'last_invoice_code']);
        });
    }
};