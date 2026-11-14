<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // سعر الإنتاج (التكلفة) لحساب صافي الربح
        Schema::table('drugs', function (Blueprint $table) {
            $table->decimal('production_price', 12, 2)->nullable()->after('price');
        });

        // السجل التاريخي لحركات المخازن
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drug_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->enum('direction', ['in', 'out']);
            $table->unsignedInteger('quantity');
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->foreignId('related_warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->nullableMorphs('reference');
            $table->string('reference_label')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['warehouse_id', 'created_at']);
            $table->index(['drug_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::table('drugs', function (Blueprint $table) {
            $table->dropColumn('production_price');
        });
    }
};
