<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centro_operaciones_reportes', function (Blueprint $table) {
            $table->string('reportado_por_nombre', 255)->nullable()->after('reportado_por_id');
        });

        Schema::table('centro_operaciones_tickets', function (Blueprint $table) {
            $table->string('codigo_validacion', 40)->nullable()->unique()->after('numero');
            $table->char('documento_hash', 64)->nullable()->after('codigo_validacion');
            $table->timestamp('documento_emitido_en')->nullable()->after('documento_hash');
        });

        Schema::create('centro_operaciones_ticket_firmas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained('centro_operaciones_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('funcionario_ac_autorizado_id')->nullable();
            $table->foreign('funcionario_ac_autorizado_id', 'co_ticket_firma_funcionario_fk')
                ->references('id')
                ->on('funcionarios_ac_autorizados')
                ->nullOnDelete();
            $table->string('tipo_firma', 50)->default('resolucion');
            $table->string('rol_firmante', 100)->nullable();
            $table->string('nombre_firmante', 255);
            $table->string('rut_firmante', 40)->nullable();
            $table->string('cargo_firmante', 255)->nullable();
            $table->string('dependencia_firmante', 255)->nullable();
            $table->string('ip_firma', 80)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('fecha_firma');
            $table->string('token_firma', 120)->unique();
            $table->char('hash_firma', 64);
            $table->timestamps();
        });

        $this->completarNombreReportante();
        $this->completarCodigosValidacion();
        $this->completarFirmasHistoricas();
    }

    public function down(): void
    {
        Schema::dropIfExists('centro_operaciones_ticket_firmas');

        Schema::table('centro_operaciones_tickets', function (Blueprint $table) {
            $table->dropUnique(['codigo_validacion']);
            $table->dropColumn(['codigo_validacion', 'documento_hash', 'documento_emitido_en']);
        });

        Schema::table('centro_operaciones_reportes', function (Blueprint $table) {
            $table->dropColumn('reportado_por_nombre');
        });
    }

    private function completarNombreReportante(): void
    {
        DB::table('centro_operaciones_reportes as reporte')
            ->join('users as usuario', 'usuario.id', '=', 'reporte.reportado_por_id')
            ->whereNull('reporte.reportado_por_nombre')
            ->orderBy('reporte.id')
            ->select([
                'reporte.id',
                'usuario.nombres',
                'usuario.apellido_paterno',
                'usuario.apellido_materno',
                'usuario.email',
            ])
            ->get()
            ->each(function (object $registro) {
                DB::table('centro_operaciones_reportes')
                    ->where('id', $registro->id)
                    ->update(['reportado_por_nombre' => $this->nombreUsuario($registro)]);
            });
    }

    private function completarCodigosValidacion(): void
    {
        DB::table('centro_operaciones_tickets')
            ->whereNull('codigo_validacion')
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $ticketId) {
                DB::table('centro_operaciones_tickets')
                    ->where('id', $ticketId)
                    ->update(['codigo_validacion' => $this->nuevoCodigoValidacion()]);
            });
    }

    private function completarFirmasHistoricas(): void
    {
        DB::table('centro_operaciones_tickets as ticket')
            ->leftJoin('users as usuario', 'usuario.id', '=', 'ticket.resuelto_por_id')
            ->where('ticket.estado', 'resuelto')
            ->whereNotNull('ticket.resuelto_por_id')
            ->orderBy('ticket.id')
            ->select([
                'ticket.id',
                'ticket.numero',
                'ticket.resuelto_por_id',
                'ticket.resuelto_en',
                'ticket.updated_at',
                'usuario.rut',
                'usuario.nombres',
                'usuario.apellido_paterno',
                'usuario.apellido_materno',
                'usuario.email',
            ])
            ->get()
            ->each(function (object $registro) {
                if (DB::table('centro_operaciones_ticket_firmas')->where('ticket_id', $registro->id)->exists()) {
                    return;
                }

                $rutNormalizado = strtoupper(preg_replace('/[^0-9Kk]/', '', (string) ($registro->rut ?? '')));
                $funcionario = DB::table('funcionarios_ac_autorizados')
                    ->where(function ($query) use ($registro, $rutNormalizado) {
                        $query->where('registered_user_id', $registro->resuelto_por_id);
                        if ($rutNormalizado !== '') {
                            $query->orWhere('rut_normalizado', $rutNormalizado);
                        }
                    })
                    ->first();
                $fecha = $registro->resuelto_en ?: $registro->updated_at ?: now();
                $token = bin2hex(random_bytes(32));
                $rut = $funcionario
                    ? trim(($funcionario->run ?? '').'-'.($funcionario->dv ?? ''), '-')
                    : ($registro->rut ?? null);

                DB::table('centro_operaciones_ticket_firmas')->insert([
                    'ticket_id' => $registro->id,
                    'user_id' => $registro->resuelto_por_id,
                    'funcionario_ac_autorizado_id' => $funcionario->id ?? null,
                    'tipo_firma' => 'resolucion',
                    'rol_firmante' => null,
                    'nombre_firmante' => $funcionario
                        ? $this->nombreUsuario($funcionario)
                        : $this->nombreUsuario($registro),
                    'rut_firmante' => $rut ?: null,
                    'cargo_firmante' => $funcionario->cargo_funcion ?? null,
                    'dependencia_firmante' => $funcionario->subdireccion_dependencia ?? null,
                    'ip_firma' => null,
                    'user_agent' => null,
                    'fecha_firma' => $fecha,
                    'token_firma' => $token,
                    'hash_firma' => hash('sha256', implode('|', [
                        $registro->id,
                        $registro->numero,
                        'resolucion',
                        $rut,
                        (string) $fecha,
                        $token,
                    ])),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    private function nuevoCodigoValidacion(): string
    {
        do {
            $codigo = 'TCO-'.strtoupper(bin2hex(random_bytes(10)));
        } while (DB::table('centro_operaciones_tickets')->where('codigo_validacion', $codigo)->exists());

        return $codigo;
    }

    private function nombreUsuario(object $usuario): string
    {
        $nombre = trim(implode(' ', array_filter([
            $usuario->nombres ?? null,
            $usuario->apellido_paterno ?? null,
            $usuario->apellido_materno ?? null,
        ])));

        return $nombre !== '' ? $nombre : ((string) ($usuario->email ?? 'Usuario registrado'));
    }
};
