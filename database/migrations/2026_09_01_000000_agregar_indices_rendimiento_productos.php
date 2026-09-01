<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->index(['nombre']);
            $table->index(['estado', 'deleted_at']);
        });

        Schema::table('producto_presentaciones', function (Blueprint $table) {
            $table->index(['activa']);
            $table->index(['producto_id', 'activa']);
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex(['nombre']);
            $table->dropIndex(['estado', 'deleted_at']);
        });

        Schema::table('producto_presentaciones', function (Blueprint $table) {
            $table->dropIndex(['activa']);
            $table->dropIndex(['producto_id', 'activa']);
        });
    }
};
