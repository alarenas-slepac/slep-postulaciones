<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CometidoNotificacionConfiguracion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class CometidoNotificacionConfiguracionController extends Controller
{
    public function index(Request $request)
    {
        CometidoNotificacionConfiguracion::sincronizarCatalogoProceso();

        $query = CometidoNotificacionConfiguracion::query()
            ->orderBy('categoria')
            ->orderBy('nombre');

        if ($request->filled('categoria')) {
            $query->where('categoria', $request->string('categoria'));
        }

        if ($request->filled('q')) {
            $busqueda = '%' . str_replace(' ', '%', trim((string) $request->q)) . '%';
            $query->where(function ($subquery) use ($busqueda) {
                $subquery->where('nombre', 'like', $busqueda)
                    ->orWhere('descripcion', 'like', $busqueda)
                    ->orWhere('clave', 'like', $busqueda)
                    ->orWhere('roles', 'like', $busqueda)
                    ->orWhere('correos', 'like', $busqueda);
            });
        }

        $configuraciones = $query->paginate(30)->withQueryString();
        $categorias = CometidoNotificacionConfiguracion::query()
            ->whereNotNull('categoria')
            ->select('categoria')
            ->distinct()
            ->orderBy('categoria')
            ->pluck('categoria');

        return view('admin.cometidos-notificaciones.index', compact('configuraciones', 'categorias'));
    }

    public function edit(CometidoNotificacionConfiguracion $cometidos_notificacione)
    {
        $configuracion = $cometidos_notificacione;

        $rolesSistema = Role::query()
            ->orderBy('name')
            ->pluck('name')
            ->values();

        $rolesSeleccionados = collect(old('roles', CometidoNotificacionConfiguracion::parseRoles($configuracion->roles)))
            ->map(fn ($rol) => strtolower(trim((string) $rol)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return view('admin.cometidos-notificaciones.edit', compact('configuracion', 'rolesSistema', 'rolesSeleccionados'));
    }

    public function update(Request $request, CometidoNotificacionConfiguracion $cometidos_notificacione)
    {
        $configuracion = $cometidos_notificacione;

        $rolesSistema = Role::query()
            ->pluck('name')
            ->map(fn ($rol) => strtolower(trim((string) $rol)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $data = $request->validate([
            'correos' => ['nullable', 'string', 'max:4000'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'max:120', Rule::in($rolesSistema)],
            'activo' => ['nullable', 'boolean'],
        ]);

        $correos = collect(preg_split('/[,;\n]+/', (string) ($data['correos'] ?? '')) ?: [])
            ->map(fn ($correo) => trim($correo))
            ->filter()
            ->values();

        $roles = collect($data['roles'] ?? [])
            ->map(fn ($rol) => strtolower(trim((string) $rol)))
            ->filter()
            ->unique()
            ->values();

        $invalidos = $correos->filter(fn ($correo) => ! filter_var($correo, FILTER_VALIDATE_EMAIL));
        if ($invalidos->isNotEmpty()) {
            return back()
                ->withInput()
                ->withErrors(['correos' => 'Revise los correos inválidos: ' . $invalidos->implode(', ')]);
        }

        if ($roles->isEmpty() && $correos->isEmpty()) {
            return back()
                ->withInput()
                ->withErrors(['correos' => 'Debe indicar al menos un rol destinatario o un correo configurable.']);
        }

        $configuracion->update([
            'correos' => $correos->unique()->implode(', '),
            'roles' => $roles->implode(', '),
            'activo' => $request->boolean('activo'),
            'updated_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.cometidos-notificaciones.index')
            ->with('success', 'Configuración de notificación actualizada correctamente.');
    }
}
