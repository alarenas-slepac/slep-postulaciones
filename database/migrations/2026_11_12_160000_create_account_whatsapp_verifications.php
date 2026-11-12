<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $name = 'account_whatsapp_verifications';
        if (! Schema::hasTable($name)) {
            Schema::create($name, function (Blueprint $t): void {
                $t->id();
                $t->unsignedBigInteger('user_id');
                $t->string('verified_phone', 16)->nullable();
                $t->timestamp('verified_at')->nullable();
                $t->string('pending_phone', 16)->nullable();
                $t->uuid('challenge_id')->nullable();
                $t->string('code_hash', 64)->nullable();
                $t->string('email_hash', 64)->nullable();
                $t->timestamp('expires_at')->nullable();
                $t->timestamp('resend_at')->nullable();
                $t->unsignedTinyInteger('attempts')->default(0);
                $t->uuid('dispatch_id')->nullable();
                $t->timestamps();
            });
        }
        // DDL de MySQL confirma cada sentencia: recuperar cada restricción por separado.
        if (! Schema::hasIndex($name, 'awv_user_uq')) {
            Schema::table($name, fn (Blueprint $t) => $t->unique('user_id', 'awv_user_uq'));
        }
        if (! Schema::hasIndex($name, 'awv_phone_uq')) {
            Schema::table($name, fn (Blueprint $t) => $t->unique('verified_phone', 'awv_phone_uq'));
        }
        if (! collect(Schema::getForeignKeys($name))->contains(fn ($fk) => $fk['columns'] === ['user_id'])) {
            Schema::table($name, fn (Blueprint $t) => $t->foreign('user_id', 'awv_user_fk')->references('id')->on('users')->restrictOnDelete());
        }
    }

    public function down(): void
    {
        // No borrar identidades confirmadas ni liberar teléfonos de cuentas históricas.
    }
};
