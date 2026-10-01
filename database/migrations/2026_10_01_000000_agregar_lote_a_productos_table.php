<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->unsignedInteger('unidades_lote')->nullable()->after('costo_usd');
            $table->decimal('precio_lote', 12, 2)->nullable()->after('unidades_lote');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['unidades_lote', 'precio_lote']);
        });
    }
};