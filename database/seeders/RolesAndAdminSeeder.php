<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RolesAndAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Crear roles (guard web)
        foreach (['admin', 'director_ejecutivo', 'funcionario_slep', 'coordinador_uatp', 'comunicaciones', 'coordinador_gdp', 'supervisor_plani', 'funcionario_estab', 'funcionario_daf', 'funcionario_juridica', 'funcionario', 'postulante'] as $name) {
            Role::findOrCreate($name, 'web');
        }

        // Admin desde .env
        $rut   = env('ADMIN_RUT', '168185502');
        $email = strtolower(trim((string) env('ADMIN_EMAIL', 'postulaciones@slepandaliencosta.gob.cl')));
        $user = User::where('email', $email)->first();

        if (!$user) {
            $user = new User();
            $user->rut = $rut;
            $user->nombres = env('ADMIN_NOMBRES', 'Admin');
            $user->apellido_paterno = env('ADMIN_APELLIDO_PATERNO', 'SLEP');
            $user->apellido_materno = env('ADMIN_APELLIDO_MATERNO', 'Root');
            $user->email = $email;
            $user->password = Hash::make(env('ADMIN_PASSWORD', 'Alla945572426*'));
            $user->email_verified_at = now();
            $user->save();
        }

        if (!$user->hasRole('admin')) {
            $user->assignRole('admin');
        }
    }
}
