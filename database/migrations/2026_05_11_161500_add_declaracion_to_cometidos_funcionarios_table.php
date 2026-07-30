<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            $table->boolean('declaracion_aceptada')->default(false)->after('solicita_reembolso');
            $table->timestamp('declaracion_aceptada_at')->nullable()->after('declaracion_aceptada');
            $table->text('declaracion_texto')->nullable()->after('declaracion_aceptada_at');
        });
    }

    public function down(): void
    {
        Schema::table('cometidos_funcionarios', function (Blueprint $table) {
            $table->dropColumn([
                'declaracion_aceptada',
                'declaracion_aceptada_at',
                'declaracion_texto',
            ]);
        });
    }
};
