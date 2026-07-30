<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Support\ModuleRegistry;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::orderBy('name')->get();
        return view('admin.roles.index', compact('roles'));
    }

    public function edit(Role $role)
    {
        // Asegura que existan módulos desde rutas
        ModuleRegistry::syncFromRoutes();

        $modules = Module::orderBy('section')->orderBy('sort')->orderBy('name')->get()
            ->groupBy('section');

        $assigned = DB::table('module_role')->where('role_id', $role->id)->pluck('module_id')->all();

        return view('admin.roles.edit', compact('role', 'modules', 'assigned'));
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'modules' => ['array'],
            'modules.*' => ['integer', 'exists:modules,id'],
        ]);

        $role->update(['name' => $data['name']]);

        $moduleIds = $data['modules'] ?? [];

        DB::table('module_role')->where('role_id', $role->id)->delete();
        $rows = array_map(fn($mid) => ['role_id' => $role->id, 'module_id' => $mid], $moduleIds);
        if ($rows) DB::table('module_role')->insert($rows);

        return redirect()->route('admin.roles.edit', $role)->with('status', 'Rol actualizado.');
    }

    public function create()
    {
        ModuleRegistry::syncFromRoutes();

        $modules = Module::orderBy('section')->orderBy('sort')->orderBy('name')->get()
            ->groupBy('section');

        $assigned = []; // vacío para create

        return view('admin.roles.create', compact('modules', 'assigned'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'modules' => ['array'],
            'modules.*' => ['integer', 'exists:modules,id'],
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);

        $moduleIds = $data['modules'] ?? [];
        $rows = array_map(fn($mid) => ['role_id' => $role->id, 'module_id' => $mid], $moduleIds);
        if ($rows) DB::table('module_role')->insert($rows);

        return redirect()->route('admin.roles.edit', $role)->with('status', 'Rol creado.');
    }

    public function destroy(Role $role)
    {
        // cuidado con borrar admin
        if ($role->name === 'admin') abort(403);
        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', 'Rol eliminado.');
    }
}
