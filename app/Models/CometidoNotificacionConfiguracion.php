<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CometidoNotificacionConfiguracion extends Model
{
    protected $table = 'cometidos_notificaciones_configuracion';

    protected $fillable = [
        'clave',
        'nombre',
        'descripcion',
        'categoria',
        'tipo_destinatario',
        'correos',
        'roles',
        'activo',
        'updated_by',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public static function correosPara(string $clave, string $default = ''): array
    {
        $registro = static::query()
            ->where('clave', $clave)
            ->where('activo', true)
            ->first();

        $valor = $registro?->correos ?: $default;

        return static::parseCorreos($valor);
    }

    public static function correosPorRoles(string $clave, array|string $rolesDefault = [], string $correosDefault = ''): array
    {
        $registro = static::query()->where('clave', $clave)->first();

        if ($registro && ! $registro->activo) {
            return [];
        }

        $rolesConfigurados = $registro ? static::parseRoles($registro->roles) : [];
        $roles = ! empty($rolesConfigurados)
            ? $rolesConfigurados
            : static::parseRoles($rolesDefault);

        $correos = $registro
            ? static::parseCorreos($registro->correos)
            : static::parseCorreos($correosDefault);

        if (! empty($roles)) {
            try {
                $usuariosRol = User::role($roles)
                    ->whereNotNull('email')
                    ->get()
                    ->pluck('email')
                    ->all();

                $correos = array_merge($correos, $usuariosRol);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return collect($correos)
            ->map(fn ($correo) => strtolower(trim((string) $correo)))
            ->filter(fn ($correo) => filter_var($correo, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    public static function correosAdicionalesPorAsunto(string $subject): array
    {
        $clave = static::claveParaAsunto($subject);
        if (! $clave) {
            return [];
        }

        $registro = static::query()
            ->where('clave', $clave)
            ->where('activo', true)
            ->first();

        if (! $registro) {
            return [];
        }

        return static::parseCorreos($registro->correos);
    }

    public static function claveParaAsunto(string $subject): ?string
    {
        $subject = trim($subject);

        return static::asuntoClaveMap()[$subject] ?? null;
    }

    public static function asuntoClaveMap(): array
    {
        return collect(static::catalogoProceso())
            ->flatMap(function (array $item, string $clave) {
                return collect($item['asuntos'] ?? [])->mapWithKeys(fn ($asunto) => [$asunto => $clave]);
            })
            ->all();
    }

    public static function parseCorreos(string|array|null $valor): array
    {
        $items = is_array($valor) ? $valor : preg_split('/[,;\n]+/', (string) $valor);

        return collect($items ?: [])
            ->map(fn ($correo) => trim((string) $correo))
            ->filter(fn ($correo) => filter_var($correo, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    public static function parseRoles(string|array|null $valor): array
    {
        $items = is_array($valor) ? $valor : preg_split('/[,;\n]+/', (string) $valor);

        return collect($items ?: [])
            ->map(fn ($rol) => Str::of((string) $rol)->trim()->lower()->replace(' ', '_')->toString())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public static function sincronizarCatalogoProceso(): void
    {
        foreach (static::catalogoProceso() as $clave => $item) {
            $registro = static::query()->firstOrNew(['clave' => $clave]);
            $nuevo = ! $registro->exists;
            $rolesDefault = implode(', ', static::parseRoles($item['roles'] ?? []));
            $correosDefault = implode(', ', static::parseCorreos($item['correos'] ?? ''));

            $registro->nombre = $item['nombre'];
            $registro->descripcion = $item['descripcion'] ?? null;
            $registro->categoria = $item['categoria'] ?? null;
            $registro->tipo_destinatario = $item['tipo_destinatario'] ?? 'rol_configurable';

            if ($nuevo) {
                $registro->roles = $rolesDefault;
                $registro->correos = $correosDefault;
                $registro->activo = true;
            } else {
                if (trim((string) $registro->roles) === '' && $rolesDefault !== '') {
                    $registro->roles = $rolesDefault;
                }
                if (trim((string) $registro->correos) === '' && $correosDefault !== '') {
                    $registro->correos = $correosDefault;
                }
                if ($registro->activo === null) {
                    $registro->activo = true;
                }
            }

            $registro->save();
        }
    }

    public static function catalogoProceso(): array
    {
        return [
            'servicios_generales_vehiculo_institucional' => [
                'nombre' => 'Servicios Generales - vehículo institucional',
                'descripcion' => 'Correo(s) que reciben aviso cuando un cometido autorizado contempla uso de vehículo institucional. Esta notificación se mantiene sólo por correo configurado.',
                'categoria' => 'Servicios Generales',
                'tipo_destinatario' => 'correo_configurable',
                'roles' => [],
                'correos' => 'johanna.isla@slepandaliencosta.gob.cl',
                'asuntos' => ['Cometido autorizado requiere coordinación de vehículo institucional'],
            ],
            'director_ejecutivo_decision_sin_disponibilidad' => [
                'nombre' => 'Director Ejecutivo - decisión por falta de disponibilidad',
                'descripcion' => 'Aviso para resolver reconversión a reembolso o rechazo cuando Planificación informa falta de presupuesto.',
                'categoria' => 'Director Ejecutivo',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['director_ejecutivo'],
                'asuntos' => ['Cometido requiere decisión del Director Ejecutivo por falta de disponibilidad'],
            ],
            'gdp_reconversion_reembolso_emitir_rex' => [
                'nombre' => 'GDP - reconversión aprobada para emitir REX CGR',
                'descripcion' => 'Aviso a GDP cuando Director Ejecutivo aprueba reconvertir viático a reembolso.',
                'categoria' => 'GDP',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['funcionario_slep', 'coordinador_gdp'],
                'asuntos' => ['Director aprobó reconversión a reembolso: emitir REX CGR'],
            ],
            'planificacion_rechazo_director_sin_disponibilidad' => [
                'nombre' => 'Planificación - rechazo Director por falta de disponibilidad',
                'descripcion' => 'Aviso a Planificación cuando Director Ejecutivo rechaza la continuidad financiera.',
                'categoria' => 'Planificación',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['supervisor_plani', 'coordinador_plani'],
                'asuntos' => ['Director rechazó continuidad financiera de cometido'],
            ],
            'planificacion_cdp_viatico_ac' => [
                'nombre' => 'Planificación - CDP viático AC',
                'descripcion' => 'Aviso cuando jefatura autoriza cometido AC con viático y requiere CDP.',
                'categoria' => 'Planificación',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['supervisor_plani', 'coordinador_plani'],
                'asuntos' => ['Cometido AC autorizado requiere CDP de viático'],
            ],
            'gdp_rex_cgr_ac_autorizado' => [
                'nombre' => 'GDP - REX cometido CGR AC autorizado',
                'descripcion' => 'Aviso cuando cometido AC autorizado contempla reembolso sin viático y requiere REX CGR.',
                'categoria' => 'GDP',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['funcionario_slep'],
                'asuntos' => ['Cometido AC autorizado requiere REX cometido CGR'],
            ],
            'daf_compra_reserva_pasaje_ac' => [
                'nombre' => 'DAF Compra - reserva pasaje aéreo',
                'descripcion' => 'Aviso cuando cometido AC autorizado contempla avión y requiere gestión de reserva.',
                'categoria' => 'Pasajes',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['funcionario_daf_compra'],
                'asuntos' => ['Cometido AC autorizado requiere reserva de pasaje aéreo'],
            ],
            'planificacion_cdp_pasaje_aereo' => [
                'nombre' => 'Planificación - CDP pasaje aéreo',
                'descripcion' => 'Aviso cuando DAF Compra carga reserva y debe emitirse CDP de pasaje.',
                'categoria' => 'Pasajes',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['supervisor_plani', 'coordinador_plani'],
                'asuntos' => ['Reserva de pasaje aéreo cargada: requiere CDP'],
            ],
            'daf_compra_cdp_pasaje_emitido' => [
                'nombre' => 'DAF Compra - CDP pasaje emitido',
                'descripcion' => 'Aviso cuando Planificación carga CDP del pasaje aéreo y DAF Compra puede registrar compra.',
                'categoria' => 'Pasajes',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['funcionario_daf_compra'],
                'asuntos' => ['CDP emitido para compra de pasaje aéreo'],
            ],
            'gdp_rex_rendicion_establecimiento' => [
                'nombre' => 'GDP - rendición establecimiento requiere REX CGR',
                'descripcion' => 'Aviso cuando establecimiento envía rendición de reembolso y GDP debe emitir REX CGR.',
                'categoria' => 'Rendición',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['funcionario_slep'],
                'asuntos' => ['Rendición enviada requiere REX cometido CGR'],
            ],
            'gdp_rex_rendicion_ac' => [
                'nombre' => 'GDP - rendición AC requiere REX CGR',
                'descripcion' => 'Aviso cuando funcionario AC envía rendición y GDP debe emitir REX CGR.',
                'categoria' => 'Rendición',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['funcionario_slep'],
                'asuntos' => ['Rendición AC enviada requiere REX cometido CGR'],
            ],
            'daf_rendicion_lista_informe' => [
                'nombre' => 'DAF - rendición e informe listos',
                'descripcion' => 'Aviso cuando la rendición está enviada y el informe aprobado, quedando lista la revisión DAF.',
                'categoria' => 'Rendición',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['funcionario_daf'],
                'asuntos' => ['Rendición e informe listos para revisión DAF', 'Rendición lista para revisión DAF'],
            ],
            'planificacion_cdp_reembolso' => [
                'nombre' => 'Planificación - CDP de reembolso',
                'descripcion' => 'Aviso cuando DAF autoriza rendición y se requiere CDP de reembolso.',
                'categoria' => 'Reembolso',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['supervisor_plani', 'coordinador_plani'],
                'asuntos' => ['Rendición autorizada por DAF: requiere CDP de reembolso'],
            ],
            'juridica_resolucion_pago_reembolso' => [
                'nombre' => 'Jurídica - resolución de pago de reembolso',
                'descripcion' => 'Aviso cuando Planificación aprueba CDP de reembolso y Jurídica debe emitir resolución.',
                'categoria' => 'Jurídica',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['juridica', 'juridico', 'abogado_juridica', 'coordinador_juridica', 'funcionario_juridica'],
                'asuntos' => ['CDP de reembolso aprobado: requiere resolución de pago'],
            ],
            'daf_contable_reembolso' => [
                'nombre' => 'DAF - compromiso y devengo de reembolso',
                'descripcion' => 'Aviso cuando Jurídica emite resolución de reembolso y DAF debe registrar compromiso/devengo.',
                'categoria' => 'Reembolso',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['funcionario_daf'],
                'asuntos' => ['Resolución de reembolso emitida: registrar compromiso y devengo'],
            ],
            'daf_pago_reembolso' => [
                'nombre' => 'DAF - pago de reembolso habilitado',
                'descripcion' => 'Aviso cuando DAF registra compromiso/devengo del reembolso y queda habilitado el pago.',
                'categoria' => 'Reembolso',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['funcionario_daf'],
                'asuntos' => ['Reembolso habilitado para pago'],
            ],
            'daf_contable_viatico' => [
                'nombre' => 'DAF - compromiso y devengo de viático',
                'descripcion' => 'Aviso cuando el cometido queda listo para registro contable de viático.',
                'categoria' => 'Viático',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['funcionario_daf'],
                'asuntos' => ['Cometido listo para registro contable de viático', 'Cometido listo para DAF contable de viático'],
            ],
            'daf_pago_viatico' => [
                'nombre' => 'DAF - pago de viático habilitado',
                'descripcion' => 'Aviso cuando DAF registra compromiso/devengo del viático y queda habilitado el pago.',
                'categoria' => 'Viático',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['funcionario_daf'],
                'asuntos' => ['Viático habilitado para pago'],
            ],
            'daf_rendicion_rex_emitida' => [
                'nombre' => 'DAF - REX CGR emitida y rendición disponible',
                'descripcion' => 'Aviso cuando GDP emite REX CGR y la rendición queda disponible para revisión DAF.',
                'categoria' => 'Rendición',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['funcionario_daf'],
                'asuntos' => ['REX cometido CGR emitida: rendición disponible para revisión DAF'],
            ],
            'informe_revision_jefatura_respaldo' => [
                'nombre' => 'Informe - revisión de jefatura respaldo',
                'descripcion' => 'Aviso por rol de respaldo cuando no se encuentra jefatura directa para revisar informe.',
                'categoria' => 'Informe',
                'tipo_destinatario' => 'rol_configurable',
                'roles' => ['coordinador_gdp', 'coordinador_uatp'],
                'asuntos' => ['Informe de Cometido pendiente de revisión de jefatura'],
            ],

            'solicitud_ac_pendiente_jefatura' => [
                'nombre' => 'AC - nueva solicitud pendiente de autorización',
                'descripcion' => 'Notificación dinámica a la jefatura autorizadora AC. Se pueden agregar correos adicionales si se requiere copia.',
                'categoria' => 'Solicitud AC',
                'tipo_destinatario' => 'dinamico_con_copia_configurable',
                'roles' => [],
                'asuntos' => ['Nueva solicitud de cometido AC pendiente de autorización'],
            ],
            'solicitud_ac_actualizada_jefatura' => [
                'nombre' => 'AC - solicitud actualizada para autorización',
                'descripcion' => 'Notificación dinámica a la jefatura autorizadora AC. Se pueden agregar correos adicionales si se requiere copia.',
                'categoria' => 'Solicitud AC',
                'tipo_destinatario' => 'dinamico_con_copia_configurable',
                'roles' => [],
                'asuntos' => ['Solicitud de cometido AC actualizada para autorización'],
            ],
            'funcionario_cometido_autorizado' => [
                'nombre' => 'Funcionario - cometido autorizado por jefatura',
                'descripcion' => 'Notificación dinámica al funcionario solicitante. Correos configurados operan como copia adicional.',
                'categoria' => 'Funcionario',
                'tipo_destinatario' => 'dinamico_con_copia_configurable',
                'roles' => [],
                'asuntos' => ['Cometido funcionario autorizado por jefatura'],
            ],
            'funcionario_informe_pendiente' => [
                'nombre' => 'Funcionario - debe completar informe',
                'descripcion' => 'Notificación dinámica al funcionario solicitante. Correos configurados operan como copia adicional.',
                'categoria' => 'Informe',
                'tipo_destinatario' => 'dinamico_con_copia_configurable',
                'roles' => [],
                'asuntos' => ['Debe completar Informe de Cometido', 'Debe completar o regularizar Informe de Cometido'],
            ],
            'funcionario_rendicion_informe_disponibles' => [
                'nombre' => 'Funcionario - rendición e informe disponibles',
                'descripcion' => 'Notificación dinámica al funcionario solicitante cuando GDP habilita rendición e informe.',
                'categoria' => 'Rendición',
                'tipo_destinatario' => 'dinamico_con_copia_configurable',
                'roles' => [],
                'asuntos' => ['Rendición e Informe de Cometido disponibles'],
            ],
            'funcionario_rex_rendicion_habilitada' => [
                'nombre' => 'Funcionario - REX CGR emitida y rendición habilitada',
                'descripcion' => 'Notificación dinámica al funcionario solicitante cuando GDP emite REX CGR y habilita rendición.',
                'categoria' => 'Rendición',
                'tipo_destinatario' => 'dinamico_con_copia_configurable',
                'roles' => [],
                'asuntos' => ['REX cometido CGR emitida: rendición habilitada'],
            ],
            'funcionario_pago_viatico_registrado' => [
                'nombre' => 'Funcionario - pago de viático registrado',
                'descripcion' => 'Notificación dinámica al funcionario solicitante cuando DAF registra pago de viático.',
                'categoria' => 'Viático',
                'tipo_destinatario' => 'dinamico_con_copia_configurable',
                'roles' => [],
                'asuntos' => ['Pago de viático registrado'],
            ],
            'funcionario_boleto_aereo_disponible' => [
                'nombre' => 'Funcionario - boleto aéreo disponible',
                'descripcion' => 'Notificación dinámica al funcionario solicitante cuando DAF Compra registra boleto disponible.',
                'categoria' => 'Pasajes',
                'tipo_destinatario' => 'dinamico_con_copia_configurable',
                'roles' => [],
                'asuntos' => ['Boleto aéreo disponible'],
            ],
            'funcionario_reconversion_reembolso' => [
                'nombre' => 'Funcionario - cometido reconvertido a reembolso',
                'descripcion' => 'Notificación dinámica al funcionario solicitante cuando Director Ejecutivo aprueba reconversión.',
                'categoria' => 'Director Ejecutivo',
                'tipo_destinatario' => 'dinamico_con_copia_configurable',
                'roles' => [],
                'asuntos' => ['Cometido reconvertido a reembolso por falta de disponibilidad'],
            ],
            'funcionario_rechazo_sin_disponibilidad' => [
                'nombre' => 'Funcionario - cometido rechazado por falta de disponibilidad',
                'descripcion' => 'Notificación dinámica al funcionario solicitante cuando Director Ejecutivo rechaza continuidad financiera.',
                'categoria' => 'Director Ejecutivo',
                'tipo_destinatario' => 'dinamico_con_copia_configurable',
                'roles' => [],
                'asuntos' => ['Cometido rechazado por falta de disponibilidad presupuestaria'],
            ],
            'funcionario_informe_aprobado' => [
                'nombre' => 'Funcionario - informe aprobado',
                'descripcion' => 'Notificación dinámica al funcionario solicitante cuando jefatura aprueba el informe.',
                'categoria' => 'Informe',
                'tipo_destinatario' => 'dinamico_con_copia_configurable',
                'roles' => [],
                'asuntos' => ['Informe de Cometido aprobado por jefatura'],
            ],
            'funcionario_informe_observado' => [
                'nombre' => 'Funcionario - informe observado',
                'descripcion' => 'Notificación dinámica al funcionario solicitante cuando jefatura observa el informe.',
                'categoria' => 'Informe',
                'tipo_destinatario' => 'dinamico_con_copia_configurable',
                'roles' => [],
                'asuntos' => ['Informe de Cometido observado por jefatura'],
            ],
            'funcionario_informe_rechazado' => [
                'nombre' => 'Funcionario - informe rechazado',
                'descripcion' => 'Notificación dinámica al funcionario solicitante cuando jefatura rechaza el informe.',
                'categoria' => 'Informe',
                'tipo_destinatario' => 'dinamico_con_copia_configurable',
                'roles' => [],
                'asuntos' => ['Informe de Cometido rechazado por jefatura'],
            ],
            'funcionario_rendicion_observada' => [
                'nombre' => 'Funcionario - rendición observada por DAF',
                'descripcion' => 'Notificación dinámica al funcionario solicitante cuando DAF observa la rendición.',
                'categoria' => 'Rendición',
                'tipo_destinatario' => 'dinamico_con_copia_configurable',
                'roles' => [],
                'asuntos' => ['Rendición observada por DAF'],
            ],
            'funcionario_rendicion_rechazada' => [
                'nombre' => 'Funcionario - rendición rechazada por DAF',
                'descripcion' => 'Notificación dinámica al funcionario solicitante cuando DAF rechaza la rendición.',
                'categoria' => 'Rendición',
                'tipo_destinatario' => 'dinamico_con_copia_configurable',
                'roles' => [],
                'asuntos' => ['Rendición rechazada por DAF'],
            ],
            'funcionario_rendicion_monto_cero' => [
                'nombre' => 'Funcionario - rendición autorizada con monto $0',
                'descripcion' => 'Notificación dinámica al funcionario solicitante cuando DAF aprueba rendición con monto $0.',
                'categoria' => 'Rendición',
                'tipo_destinatario' => 'dinamico_con_copia_configurable',
                'roles' => [],
                'asuntos' => ['Rendición autorizada con monto $0'],
            ],
            'funcionario_pago_reembolso_registrado' => [
                'nombre' => 'Funcionario - pago de reembolso registrado',
                'descripcion' => 'Notificación dinámica al funcionario solicitante cuando DAF registra pago de reembolso.',
                'categoria' => 'Reembolso',
                'tipo_destinatario' => 'dinamico_con_copia_configurable',
                'roles' => [],
                'asuntos' => ['Pago de reembolso registrado'],
            ],
            'establecimiento_cierre_cometido' => [
                'nombre' => 'Establecimiento - cierre de cometido',
                'descripcion' => 'Notificación dinámica a correos del establecimiento cuando se cierra la rendición/reembolso.',
                'categoria' => 'Cierre',
                'tipo_destinatario' => 'dinamico_establecimiento_con_copia_configurable',
                'roles' => [],
                'asuntos' => ['Cometido funcionario cerrado'],
            ],
        ];
    }
}
