<?php

namespace App\Http\Controllers;

use App\Models\AdmisionEstablecimiento;
use App\Models\AdmisionEstablecimientoImagen;
use App\Models\Establecimiento;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdmisionEscolarPublicController extends Controller
{
    public function index(Request $request): View
    {
        if (! config('admision.publica_habilitada', false)) {
            abort_unless(config('admision.mostrar_proximamente', true), 404);

            return view('public.admision-escolar.coming-soon');
        }

        $q = trim((string) $request->query('q', ''));
        $comuna = trim((string) $request->query('comuna', ''));
        $nivel = trim((string) $request->query('nivel', ''));
        $tipo = trim((string) $request->query('tipo', ''));
        $sector = trim((string) $request->query('sector', ''));
        $orden = trim((string) $request->query('orden', 'destacados'));

        $levelColumns = [
            'sala_cuna' => 'sala_cuna',
            'pre_escolar' => 'pre_escolar',
            'basica' => 'basica',
            'media' => 'media',
            'tecnico_profesional' => 'tecnico_profesional',
            'adultos' => 'adultos',
            'especial' => 'especial',
        ];

        $query = AdmisionEstablecimiento::query()
            ->publicados()
            ->with(['establecimiento', 'portada'])
            ->when($q !== '', function (Builder $query) use ($q) {
                $query->where(function (Builder $inner) use ($q) {
                    $inner->where('sello_educativo', 'like', "%{$q}%")
                        ->orWhere('descripcion_corta', 'like', "%{$q}%")
                        ->orWhereHas('establecimiento', function (Builder $establecimiento) use ($q) {
                            $establecimiento->where('nombre_establecimiento', 'like', "%{$q}%")
                                ->orWhere('rbd', 'like', "%{$q}%");
                        });
                });
            })
            ->when($comuna !== '', fn (Builder $query) => $query->whereHas(
                'establecimiento',
                fn (Builder $establecimiento) => $establecimiento->where('comuna', $comuna)
            ))
            ->when(isset($levelColumns[$nivel]), fn (Builder $query) => $query->whereHas(
                'establecimiento',
                fn (Builder $establecimiento) => $establecimiento->where($levelColumns[$nivel], true)
            ))
            ->when($tipo !== '', fn (Builder $query) => $query->whereHas(
                'establecimiento',
                fn (Builder $establecimiento) => $establecimiento->where('tipo_estab', $tipo)
            ))
            ->when(in_array($sector, ['Urbano', 'Rural'], true), fn (Builder $query) => $query->where('sector', $sector));

        $establishmentNameSubquery = Establecimiento::query()
            ->select('nombre_establecimiento')
            ->whereColumn('establecimientos.id', 'admision_establecimientos.establecimiento_id')
            ->limit(1);

        $establishmentCommuneSubquery = Establecimiento::query()
            ->select('comuna')
            ->whereColumn('establecimientos.id', 'admision_establecimientos.establecimiento_id')
            ->limit(1);

        match ($orden) {
            'nombre' => $query->orderBy($establishmentNameSubquery),
            'comuna' => $query->orderBy($establishmentCommuneSubquery)->orderBy($establishmentNameSubquery),
            default => $query->orderByDesc('destacado')->orderBy('orden')->orderBy($establishmentNameSubquery),
        };

        $items = $query
            ->paginate(max(1, (int) config('admision.por_pagina', 12)))
            ->withQueryString();

        $publishedProfiles = AdmisionEstablecimiento::query()->publicados();
        $summary = [
            'establecimientos' => (clone $publishedProfiles)->count(),
            'comunas' => Establecimiento::query()
                ->whereHas('admisionPerfil', fn (Builder $perfil) => $perfil->publicados())
                ->whereNotNull('comuna')
                ->distinct()
                ->count('comuna'),
        ];

        $comunas = Establecimiento::query()
            ->whereHas('admisionPerfil', fn (Builder $perfil) => $perfil->publicados())
            ->whereNotNull('comuna')
            ->where('comuna', '<>', '')
            ->distinct()
            ->orderBy('comuna')
            ->pluck('comuna');

        $tipos = Establecimiento::query()
            ->whereHas('admisionPerfil', fn (Builder $perfil) => $perfil->publicados())
            ->whereNotNull('tipo_estab')
            ->where('tipo_estab', '<>', '')
            ->distinct()
            ->orderBy('tipo_estab')
            ->pluck('tipo_estab');

        $heroImages = AdmisionEstablecimientoImagen::query()
            ->where('es_portada', true)
            ->whereHas('admisionEstablecimiento', fn (Builder $perfil) => $perfil->publicados())
            ->orderByDesc('updated_at')
            ->limit(3)
            ->get();

        return view('public.admision-escolar.index', compact(
            'items',
            'summary',
            'comunas',
            'tipos',
            'heroImages',
            'q',
            'comuna',
            'nivel',
            'tipo',
            'sector',
            'orden'
        ));
    }

    public function show(string $slug): View
    {
        abort_unless(config('admision.publica_habilitada', false), 404);

        $perfil = AdmisionEstablecimiento::query()
            ->publicados()
            ->where('slug', $slug)
            ->with(['establecimiento', 'imagenes'])
            ->firstOrFail();

        return view('public.admision-escolar.show', [
            'perfil' => $perfil,
            'establecimiento' => $perfil->establecimiento,
            'isPreview' => false,
        ]);
    }
}
