<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establecimientos', function (Blueprint $table) {
            $table->decimal('latitud', 10, 7)->nullable()->after('asignacion_zona');
            $table->decimal('longitud', 10, 7)->nullable()->after('latitud');
        });
    }

    public function down(): void
    {
        Schema::table('establecimientos', function (Blueprint $table) {
            $table->dropColumn(['latitud', 'longitud']);
        });
    }
};
