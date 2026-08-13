<?php

namespace App\Support;

use App\Models\Module;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class ModuleRegistry
{
    public static function moduleKeyFromRouteName(string $name): ?string
    {
        if (Str::startsWith($name, 'reemplazos.personal.')) return 'reemplazos.personal';
        if (Str::startsWith($name, 'reemplazos.')) return 'reemplazos';
        if (Str::startsWith($name, 'gestion.autorizaciones-docentes.')) return 'gestion.solicitudes-reemplazo';
        if (Str::startsWith($name, 'gestion.deudas-pension-alimentos.')) return 'gestion.solicitudes-reemplazo';
        if (Str::startsWith($name, 'postulant.deudas-pension-alimentos.')) return 'postulant.reemplazos';

        // messages.* -> messages
        if (Str::startsWith($name, 'messages.')) return 'messages';

        $parts = explode('.', $name);
        if (count($parts) === 0) return null;

        // admin.xxx.* -> admin.xxx
        if ($parts[0] === 'admin' && isset($parts[1])) return "admin.{$parts[1]}";

        // funcionario.xxx.* -> funcionario.xxx
        if ($parts[0] === 'funcionario' && isset($parts[1])) return "funcionario.{$parts[1]}";

        // postulant.xxx.* -> postulant.xxx
        if ($parts[0] === 'postulant' && isset($parts[1])) return "postulant.{$parts[1]}";

        // ✅ NUEVO - GESTION REEMPLAZOS
        if ($parts[0] === 'gestion' && isset($parts[1])) return "gestion.{$parts[1]}";

        // reemplazos.* -> reemplazos
        return $parts[0];
    }

    public static function defaultMeta(string $key): array
    {
        $section = match (true) {
            in_array($key, ['centro-operaciones', 'reemplazos', 'reemplazos.personal', 'admin.permiso-sin-goce-excepciones', 'incumplimientos', 'endeudamiento', 'declaracion', 'funcionario.solicitudes-reemplazo', 'gestion.solicitudes-reemplazo', 'gestion.estadisticas', 'gestion.informes'], true) => 'Operación',
            Str::startsWith($key, 'admin.establecimientos') ||
                Str::startsWith($key, 'admin.alumnos-prioritarios') ||
                Str::startsWith($key, 'admin.cursos') ||
                Str::startsWith($key, 'admin.planes-estudio') ||
                Str::startsWith($key, 'admin.establecimiento-cursos') ||
                Str::startsWith($key, 'admin.establecimiento-curso-pie') ||
                Str::startsWith($key, 'admin.dotacion-funciones') ||
                Str::startsWith($key, 'admin.dotacion-establecimiento') ||
                Str::startsWith($key, 'admin.establecimiento-planes') ||
                Str::startsWith($key, 'admin.asignaturas-personalizadas') ||
                Str::startsWith($key, 'admin.asignaturas') ||
                Str::startsWith($key, 'admin.areas-desempeno') ||
                Str::startsWith($key, 'admin.aaee-valores-hora') ||
                Str::startsWith($key, 'admin.viaticos-reembolsos') ||
                Str::startsWith($key, 'admin.funcionarios-viatico-anexo') ||
                Str::startsWith($key, 'admin.menciones') ||
                Str::startsWith($key, 'admin.subsectores') => 'Catálogos',
            Str::startsWith($key, 'admin.documents') => 'Revisión',
            $key === 'admin.admision-escolar' => 'Administración',
            $key === 'messages' => 'Comunicación',
            Str::startsWith($key, 'admin.users') || Str::startsWith($key, 'admin.roles') || Str::startsWith($key, 'admin.restricted-ruts') || Str::startsWith($key, 'admin.notification-logs') || Str::startsWith($key, 'admin.bulk-role-mail') => 'Administración',
            Str::startsWith($key, 'postulant.profile') || Str::startsWith($key, 'postulant.documents') || Str::startsWith($key, 'postulant.reemplazos') || Str::startsWith($key, 'postulant.ofertas-laborales') => 'Postulación',
            $key === 'tramites' => 'Trámites',
            default => 'Otros',
        };

        $name = match ($key) {
            'centro-operaciones' => 'Centro de Operaciones',
            'reemplazos.personal' => 'Carga Masiva Personal',
            'incumplimientos' => 'Incumplimiento Laboral',
            'endeudamiento' => 'Endeudamiento',
            'declaracion' => 'Declaración de Sostenedores',
            'funcionario.solicitudes-reemplazo' => 'Solicitudes de Reemplazo',
            'gestion.solicitudes-reemplazo' => 'Gestión Solicitudes',
            'gestion.estadisticas' => 'Estadísticas',
            'gestion.informes' => 'Informes',
            'gestion.bolsa-trabajo' => 'Bolsa de Trabajo',
            'postulant.ofertas-laborales' => 'Ofertas Laborales',
            'admin.restricted-ruts' => 'Restricciones para ejercer',
            'admin.permiso-sin-goce-excepciones' => 'Excepciones permiso sin goce',
            'admin.notification-logs' => 'Historial de notificaciones',
            'admin.bulk-role-mail' => 'Correos masivos por rol',
            'admin.admision-escolar' => 'Admisión Escolar',
            'admin.viaticos-reembolsos' => 'Viáticos y Reembolsos',
            'admin.funcionarios-viatico-anexo' => 'Funcionarios viático por anexo',
            'admin.alumnos-prioritarios' => 'Porcentaje Alumnos Prioritarios',
            'admin.cursos' => 'Cursos',
            'admin.planes-estudio' => 'Planes de Estudio',
            'admin.establecimiento-cursos' => 'Cursos por Establecimiento',
            'admin.establecimiento-curso-pie' => 'Estudiantes PIE por curso',
            'admin.dotacion-funciones' => 'Dotación funciones y planes',
            'admin.dotacion-establecimiento' => 'Dotación establecimiento',
            'admin.establecimiento-planes' => 'Configuración de Planes por Establecimiento',
            'admin.asignaturas-personalizadas' => 'Asignaturas Personalizadas',
            'admin.asignaturas' => 'Asignaturas',
            'postulant.reemplazos' => 'Mis Reemplazos',
            'tramites' => 'Trámites',
            default => (string) Str::of($key)
                ->replace(['admin.', 'postulant.', 'funcionario.', 'gestion.'], '')
                ->replace('-', ' ')
                ->title(),
        };

        $sort = match ($key) {
            'centro-operaciones' => 5,
            'reemplazos' => 10,
            'reemplazos.personal' => 20,
            'admin.permiso-sin-goce-excepciones' => 22,
            'incumplimientos' => 25,
            'endeudamiento' => 27,
            'declaracion' => 28,
            'funcionario.solicitudes-reemplazo' => 30,
            'gestion.solicitudes-reemplazo' => 40,
            'gestion.estadisticas' => 50,
            'gestion.informes' => 60,
            'gestion.bolsa-trabajo' => 58,
            'postulant.reemplazos' => 35,
            'postulant.ofertas-laborales' => 36,
            'tramites' => 38,
            'admin.restricted-ruts' => 70,
            'admin.notification-logs' => 75,
            'admin.bulk-role-mail' => 74,
            'admin.admision-escolar' => 15,
            'admin.viaticos-reembolsos' => 24,
            'admin.funcionarios-viatico-anexo' => 24,
            'admin.alumnos-prioritarios' => 25,
            'admin.cursos' => 26,
            'admin.planes-estudio' => 27,
            'admin.establecimiento-cursos' => 28,
            'admin.establecimiento-curso-pie' => 29,
            'admin.dotacion-funciones' => 32,
            'admin.dotacion-establecimiento' => 33,
            'admin.establecimiento-planes' => 30,
            'admin.asignaturas-personalizadas' => 31,
            'admin.asignaturas' => 30,
            default => 100,
        };

        return [
            'name' => $name,
            'section' => $section,
            'icon' => null,
            'sort' => $sort,
        ];
    }

    public static function syncFromRoutes(): int
    {
        $count = 0;

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (!$name) continue;

            $key = self::moduleKeyFromRouteName($name);
            if (!$key) continue;

            $meta = self::defaultMeta($key);

            $module = Module::firstOrCreate(
                ['key' => $key],
                ['name' => $meta['name'], 'section' => $meta['section'], 'icon' => $meta['icon'], 'sort' => $meta['sort']]
            );

            // si existe pero está en "Otros" y ahora calza con regla, opcionalmente actualizar:
            // (lo dejo conservador; puedes habilitar si quieres)
        }

        return $count;
    }
}
