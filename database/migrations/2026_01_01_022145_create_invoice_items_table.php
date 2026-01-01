<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->string('ingredient_name');
            $table->string('normalized_name')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('quantity')->nullable();
            $table->string('unit')->nullable(); // kg, L, piece
            $table->decimal('divider', 10, 2)->default(1);
            $table->decimal('price_per_kg', 10, 2);
            $table->unsignedBigInteger('existing_ingredient_id')->nullable();
            $table->integer('similarity_score')->nullable();
            $table->boolean('is_new')->default(true);
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('existing_ingredient_id')->references('id')->on('ingredients')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};