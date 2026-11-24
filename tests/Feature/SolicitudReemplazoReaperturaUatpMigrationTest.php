<?php

namespace Tests\Feature;

use App\Http\Controllers\Gestion\SolicitudReemplazoGestionController;
use App\Models\SolicitudReemplazo;
use App\Services\WhatsApp\ReemplazoWhatsAppNotifier;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\IsolatedSecurityTestCase;

class SolicitudReemplazoReaperturaUatpMigrationTest extends IsolatedSecurityTestCase
{
    private const TABLA = 'solicitudes_reemplazo';

    private const ORIGINAL = '2026_07_01_010000_add_reapertura_planificacion_to_solicitudes_reemplazo';

    private const REPARACION = 'database/migrations/2026_11_24_140000_restore_uatp_reapertura_columns_to_solicitudes_reemplazo.php';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create(self::TABLA, function (Blueprint $table): void {
            $table->id();
            $table->string('numero_solicitud');
            $table->string('estado');
            $table->text('motivo_rechazo')->nullable();
            $table->text('justificacion_tecnica_uatp')->nullable();
            $table->text('plani_motivo_rechazo')->nullable();
            $table->unsignedBigInteger('uatp_decision_user_id')->nullable();
            $table->unsignedBigInteger('plani_decision_user_id')->nullable();
            $table->timestamp('uatp_decision_at')->nullable();
            $table->timestamp('plani_decision_at')->nullable();
            $table->timestamps();
        });
        DB::table(self::TABLA)->insert([
            'id' => 1, 'numero_solicitud' => 'PRUEBA-1', 'estado' => 'rechazada_uatp',
            'motivo_rechazo' => 'Motivo original de prueba',
        ]);
    }

    public function test_repara_y_permite_reabrir_cuando_la_migracion_original_figura_aplicada(): void
    {
        $this->assertSame(0, Artisan::call('migrate:install'));
        DB::table('migrations')->insert(['migration' => self::ORIGINAL, 'batch' => 1]);
        $this->assertFalse(Schema::hasColumn(self::TABLA, 'uatp_rechazo_reabierto_motivo'));
        $this->assertSame(0, Artisan::call('migrate', ['--path' => [self::REPARACION], '--force' => true]));
        $this->assertDatabaseHas(self::TABLA, ['id' => 1, 'estado' => 'rechazada_uatp', 'motivo_rechazo' => 'Motivo original de prueba']);

        $usuario = $this->testUser(role: 3);
        $request = Request::create('/uatp/reabrir', 'POST', ['uatp_reapertura_motivo' => 'Fundamento de prueba']);
        $request->setUserResolver(fn () => $usuario);
        $this->mock(ReemplazoWhatsAppNotifier::class)->shouldReceive('notify')->once()->withArgs(
            fn (SolicitudReemplazo $solicitud, string $evento) => $solicitud->id === 1 && $evento === 'reabierta_uatp'
        );

        $solicitud = SolicitudReemplazo::findOrFail(1);
        $respuesta = app(SolicitudReemplazoGestionController::class)->uatpReabrir($request, $solicitud);

        $this->assertSame(302, $respuesta->getStatusCode());
        $solicitud->refresh();
        $this->assertSame('pendiente_uatp', $solicitud->estado);
        $this->assertNull($solicitud->motivo_rechazo);
        $this->assertSame('Motivo original de prueba', $solicitud->uatp_rechazo_reabierto_motivo);
        $this->assertSame('Fundamento de prueba', $solicitud->uatp_reapertura_motivo);
        $this->assertEquals($usuario->id, $solicitud->uatp_reapertura_user_id);
        $this->assertNotNull($solicitud->uatp_reapertura_at);
    }

    public function test_reintento_completa_columnas_y_clave_sin_duplicar_el_indice_ni_perder_la_trazabilidad(): void
    {
        Schema::table(self::TABLA, function (Blueprint $table): void {
            $table->text('uatp_rechazo_reabierto_motivo')->nullable();
            $table->unsignedBigInteger('uatp_reapertura_user_id')->nullable();
            $table->index(['uatp_reapertura_user_id', 'estado'], 'sr_indice_historico');
        });
        DB::table('users')->insert(['id' => 1, 'email' => 'prueba@example.test', 'password' => 'prueba']);
        DB::table(self::TABLA)->where('id', 1)->update([
            'uatp_rechazo_reabierto_motivo' => 'Dato histórico de prueba', 'uatp_reapertura_user_id' => 1,
        ]);

        $migration = require base_path(self::REPARACION);
        $migration->up();
        $migration->up();
        $migration->down();

        $this->assertTrue(Schema::hasColumn(self::TABLA, 'uatp_reapertura_at'));
        $this->assertTrue(Schema::hasIndex(self::TABLA, 'sr_indice_historico'));
        $this->assertFalse(Schema::hasIndex(self::TABLA, 'sr_uatp_reapertura_usuario_idx'));
        $claves = Schema::getForeignKeys(self::TABLA);
        $this->assertCount(1, $claves);
        $this->assertSame(['uatp_reapertura_user_id'], $claves[0]['columns']);
        $this->assertDatabaseHas(self::TABLA, [
            'id' => 1, 'uatp_rechazo_reabierto_motivo' => 'Dato histórico de prueba',
            'uatp_reapertura_user_id' => 1, 'uatp_reapertura_motivo' => null,
        ]);
        DB::table('users')->where('id', 1)->delete();
        $this->assertDatabaseHas(self::TABLA, ['id' => 1, 'uatp_reapertura_user_id' => null]);
    }

    public function test_sql_mysql_con_prefijo_usa_nombres_explicitos_y_aplica_solo_ddl_aditivo(): void
    {
        $connection = new MySqlConnection(null, 'sga_sintetica', 'sga_', ['prefix_indexes' => true]);
        $connection->useDefaultSchemaGrammar();
        $statements = [];
        Schema::clearResolvedInstance('db.schema');
        $this->app->forgetInstance('db.schema');
        Schema::shouldReceive('hasTable')->once()->with(self::TABLA)->andReturn(true);
        Schema::shouldReceive('getColumnListing')->once()->with(self::TABLA)->andReturn(['id']);
        Schema::shouldReceive('getIndexes', 'getForeignKeys')->once()->with(self::TABLA)->andReturn([]);
        Schema::shouldReceive('table')->times(3)->with(self::TABLA, \Mockery::type(\Closure::class))
            ->andReturnUsing(function (string $table, \Closure $definition) use ($connection, &$statements): void {
                $blueprint = new Blueprint($connection, $table, $definition);
                array_push($statements, ...$blueprint->toSql());
            });

        (require base_path(self::REPARACION))->up();

        $sql = implode("\n", $statements);
        $this->assertStringContainsString('alter table `sga_solicitudes_reemplazo` add `uatp_rechazo_reabierto_motivo` text null', $sql);
        $this->assertStringContainsString('add index `sr_uatp_reapertura_usuario_idx`', $sql);
        $this->assertStringContainsString('add constraint `sr_uatp_reapertura_usuario_fk` foreign key (`uatp_reapertura_user_id`) references `sga_users` (`id`) on delete set null', $sql);
        $this->assertStringNotContainsString('drop ', $sql);
        preg_match_all('/`([^`]+)`/', $sql, $identifiers);
        foreach (array_unique($identifiers[1]) as $identifier) {
            $this->assertLessThanOrEqual(64, strlen($identifier), $identifier);
        }
    }
}
