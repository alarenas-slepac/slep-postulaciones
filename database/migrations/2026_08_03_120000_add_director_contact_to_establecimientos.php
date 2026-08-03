<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('establecimientos')) {
            return;
        }

        $addDirectorNombre = ! Schema::hasColumn('establecimientos', 'director_nombre');
        $addDirectorContacto = ! Schema::hasColumn('establecimientos', 'director_contacto');

        if ($addDirectorNombre || $addDirectorContacto) {
            Schema::table('establecimientos', function (Blueprint $table) use ($addDirectorNombre, $addDirectorContacto) {
                if ($addDirectorNombre) {
                    $table->string('director_nombre', 180)->nullable()->after('nombre_establecimiento');
                }

                if ($addDirectorContacto) {
                    $table->string('director_contacto', 255)->nullable()->after('director_nombre');
                }
            });
        }

        $this->backfillFromAdmisionEscolar();
    }

    public function down(): void
    {
        if (! Schema::hasTable('establecimientos')) {
            return;
        }

        $columns = collect(['director_nombre', 'director_contacto'])
            ->filter(fn (string $column) => Schema::hasColumn('establecimientos', $column))
            ->all();

        if ($columns !== []) {
            Schema::table('establecimientos', fn (Blueprint $table) => $table->dropColumn($columns));
        }
    }

    private function backfillFromAdmisionEscolar(): void
    {
        if (
            ! Schema::hasTable('admision_establecimientos')
            || ! Schema::hasColumn('admision_establecimientos', 'establecimiento_id')
            || ! Schema::hasColumn('admision_establecimientos', 'director_nombre')
        ) {
            return;
        }

        $hasEmail = Schema::hasColumn('admision_establecimientos', 'email_publico');
        $hasTelefono = Schema::hasColumn('admision_establecimientos', 'telefono_publico');
        $columns = ['id', 'establecimiento_id', 'director_nombre'];

        if ($hasEmail) {
            $columns[] = 'email_publico';
        }
        if ($hasTelefono) {
            $columns[] = 'telefono_publico';
        }

        DB::table('admision_establecimientos')
            ->select($columns)
            ->orderBy('id')
            ->chunkById(200, function ($perfiles) use ($hasEmail, $hasTelefono): void {
                foreach ($perfiles as $perfil) {
                    $directorNombre = trim((string) ($perfil->director_nombre ?? ''));
                    $email = $hasEmail ? trim((string) ($perfil->email_publico ?? '')) : '';
                    $telefono = $hasTelefono ? trim((string) ($perfil->telefono_publico ?? '')) : '';
                    $directorContacto = $email !== '' ? $email : ($telefono !== '' ? $telefono : null);

                    $establecimiento = DB::table('establecimientos')
                        ->where('id', $perfil->establecimiento_id);

                    if ($directorNombre !== '') {
                        (clone $establecimiento)
                            ->where(function ($query) {
                                $query->whereNull('director_nombre')->orWhere('director_nombre', '');
                            })
                            ->update(['director_nombre' => $directorNombre]);
                    }

                    if ($directorContacto !== null) {
                        (clone $establecimiento)
                            ->where(function ($query) {
                                $query->whereNull('director_contacto')->orWhere('director_contacto', '');
                            })
                            ->update(['director_contacto' => $directorContacto]);
                    }
                }
            }, 'id');
    }
};
