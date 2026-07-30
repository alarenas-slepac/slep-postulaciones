<?php

namespace App\Http\Controllers;

use App\Http\Requests\PostulantProfileRequest;
use App\Models\Commune;
use App\Support\PdfBranding;
use App\Models\PostulantProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Support\ProfileChecklist;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\AreaDesempeno;
use Illuminate\Validation\Rule;

class PostulantProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();

        // Crea perfil si no existe (prefill email_contacto)
        $profile = $user->postulantProfile ?: new PostulantProfile([
            'user_id' => $user->id,
            'email_contacto' => $user->email,
        ]);

        // Catálogos frontend
        $regiones = config('chile.regiones', []);
        // 1) Colección de nacionalidades desde config
        $nacionalidades = collect(config('nacionalidades', []));
        // 2) Nacionalidad seleccionada (old → valor del usuario → CL)
        $selNac = old('nacionalidad', $user->nacionalidad ?? 'CL');

        // 3) $initial como arreglo seguro
        $initial = $nacionalidades->firstWhere('value', $selNac) ?? [
            'value' => 'CL',
            'iso' => 'cl',
            'abbr' => 'CHL',
            'emoji' => '🇨🇱',
            'name' => 'Chilena',
        ];

        $generos = ['masculino', 'femenino', 'otro'];
        $pronombres = ['él', 'ella', 'elle', 'él/ella', 'ella/elle', 'él/elle'];
        $areasDocente = AreaDesempeno::activos()->docente()->orderBy('nombre')->get(['id', 'nombre']);
        $areasAsistente = AreaDesempeno::activos()->asistente()->orderBy('nombre')->get(['id', 'nombre']);
        $nivelesTodos = ['Enseñanza Media', 'Enseñanza Media Laboral', 'Enseñanza Media TP', 'Técnico Nivel Superior', 'Universitaria'];

        // Comunas por región para dirección (desde DB para validar id)
        $communesByRegion = Commune::query()
            ->orderBy('region_code')->orderBy('name')
            ->get()
            ->groupBy('region_code')
            ->map(fn($c) => $c->map(fn($x) => ['id' => $x->id, 'name' => $x->name])->values())
            ->toArray();

        // Comunas totales para "lugares de desempeño" (checkboxes)
        $allCommunes = Commune::orderBy('name')->get();
        $afps = ['AFP Capital', 'AFP Cuprum', 'AFP Habitat', 'AFP Modelo', 'AFP PlanVital', 'AFP Provida', 'AFP Uno', 'IPS (ex-INP)', 'Nunca he cotizado'];
        $salud = ['FONASA', 'Isapre Banmédica', 'Isapre Colmena', 'Isapre Consalud', 'Isapre Cruz Blanca', 'Isapre Nueva Masvida', 'Isapre Vida Tres', 'Nunca he cotizado'];
        $bancos = [
            'Banco de Chile',
            'BancoEstado',
            'Scotiabank',
            'BCI',
            'Corpbanca',
            'Banco BICE',
            'Banco Santander',
            'Banco Itaú',
            'Banco Security',
            'Banco Falabella',
            'Banco Ripley',
            'Rabobank Chile',
            'Banco Consorcio',
            'Banco BBVA',
            'Coopeuch',
            'Tenpo',
            'Tapp Caja Los Andes',
            'Copec Pay',
            'American Express',
            'Mercado Pago',
        ];
        $tiposCuenta = ['Cuenta Corriente', 'Cuenta Vista', 'Cuenta RUT', 'Chequera Electrónica'];

        $check = ProfileChecklist::compute($user);

        return view('postulant.profile.edit', compact(
            'user',
            'profile',
            'regiones',
            'nacionalidades',
            'selNac',
            'initial',
            'generos',
            'pronombres',
            'areasDocente',
            'areasAsistente',
            'nivelesTodos',
            'communesByRegion',
            'allCommunes',
            'afps',
            'salud',
            'bancos',
            'tiposCuenta',
            'check'
        ));
    }

    public function update(PostulantProfileRequest $request, int $user)
    {
        $authUser = $request->user();

        DB::transaction(function () use ($request, $authUser) {
            // 1) Todo lo validado (incluye previsional/bancario si tu FormRequest ya lo tiene)
            $data = $request->validated();

            // Fallback defensivo
            $data['email_contacto'] = $data['email_contacto'] ?? ($authUser->email ?? null);

            // 2) Restringir "lugares" a las comunas permitidas (además de lo ya validado en FormRequest)
            $permitidas    = config('chile.comunas_postulacion_permitidas', ['Coronel', 'Lota', 'San Pedro de la Paz', 'Santa Juana', 'Isla Santa María']);
            $permitidasIds = Commune::whereIn('name', $permitidas)
                ->pluck('id')
                ->map(fn($v) => (int) $v)
                ->all();
            $currentIds = $authUser->communes()
                ->pluck('communes.id')
                ->map(fn($v) => (int) $v)
                ->all();
            $allowedIds = array_values(array_unique(array_merge($permitidasIds, $currentIds)));

            $request->validate([
                'lugares'   => ['required', 'array', 'min:1'],
                'lugares.*' => ['integer', Rule::in($allowedIds)],
            ]);

            $ids = array_values(array_unique(array_map('intval', $request->input('lugares', []))));

            $authUser->communes()->sync($ids);

            // 3) Cargar/crear perfil en una sola operación
            $profile = $authUser->postulantProfile()->firstOrNew(['user_id' => $authUser->id]);

            // Recordar fotos previas (para limpiar después si cambiaron)
            $oldPhoto = $profile->foto_path ?? null;
            $oldThumb = $profile->foto_thumb_path ?? null;

            // 4) Procesar foto (opcional) -> agregar rutas al $data; guardaremos todo junto más abajo
            if ($request->hasFile('foto')) {
                $file = $request->file('foto');

                // 4.1 Guarda original
                $path = $file->store('profile_photos', 'public');

                // 4.2 Construye ruta de thumbnail
                $inExt   = strtolower($file->getClientOriginalExtension() ?: 'jpg');
                $name    = pathinfo($path, PATHINFO_FILENAME);
                $outExt  = in_array($inExt, ['jpg', 'jpeg']) ? 'jpg' : (in_array($inExt, ['png', 'webp']) ? $inExt : 'jpg');
                $thumbRel = "profile_photos/thumbs/{$name}-80x80.{$outExt}";

                // 4.3 Genera thumbnail 80x80 (corrige orientación y recorta)
                $img = \Intervention\Image\Laravel\Facades\Image::read($file->getPathname())
                    ->orient()
                    ->cover(80, 80);

                $binary = $outExt === 'png'
                    ? (string) $img->toPng()
                    : ($outExt === 'webp' ? (string) $img->toWebp(85) : (string) $img->toJpeg(85));

                \Illuminate\Support\Facades\Storage::disk('public')->put($thumbRel, $binary);

                // 4.4 Agrega al payload (se persistirá junto con el resto de campos)
                $data['foto_path']       = $path;
                $data['foto_thumb_path'] = $thumbRel;
            }

            // 5) Guardar/actualizar perfil (mass assignment de TODO lo validado)
            $profile->fill($data)->save();

            // 6) Sincronizar "lugares" (pivot)
            $authUser->communes()->sync($ids);

            // 7) Limpiar archivos previos solo si cambiaron y existen
            if ($request->hasFile('foto')) {
                if ($oldPhoto && $oldPhoto !== ($profile->foto_path ?? null) && \Illuminate\Support\Facades\Storage::disk('public')->exists($oldPhoto)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPhoto);
                }
                if ($oldThumb && $oldThumb !== ($profile->foto_thumb_path ?? null) && \Illuminate\Support\Facades\Storage::disk('public')->exists($oldThumb)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($oldThumb);
                }
            }
        });

        return redirect()->route('postulant.profile.edit')
            ->with('status', 'Perfil actualizado correctamente.');
    }


    public function destroy(Request $request, User $user)
    {
        // Validar contraseña del admin que ejecuta
        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        // Verificar password del admin autenticado
        if (! Hash::check($data['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'Contraseña incorrecta.'])->withInput();
        }

        // Prevenir auto-eliminación y/o eliminar último admin (opcional)
        if ($request->user()->is($user)) {
            return back()->withErrors(['general' => 'No puedes eliminar tu propia cuenta.']);
        }
        // Si usas Spatie y quieres evitar eliminar al último admin:
        // if ($user->hasRole('admin') && \App\Models\User::role('admin')->count() <= 1) {
        //     return back()->withErrors(['general' => 'No puedes eliminar al último administrador.']);
        // }

        DB::transaction(function () use ($request, $user) {
            // 1) Auditoría en el propio registro del usuario
            $user->deleted_by = $request->user()->id;

            // 2) Borrar archivos asociados (ej. fotos de perfil)
            if ($user->relationLoaded('postulantProfile')) {
                $profile = $user->postulantProfile;
            } else {
                $profile = $user->postulantProfile()->first();
            }
            if ($profile) {
                if (!empty($profile->foto_path) && Storage::disk('public')->exists($profile->foto_path)) {
                    Storage::disk('public')->delete($profile->foto_path);
                }
                if (!empty($profile->foto_thumb_path) && Storage::disk('public')->exists($profile->foto_thumb_path)) {
                    Storage::disk('public')->delete($profile->foto_thumb_path);
                }
                // Si PostulantProfile usa SoftDeletes y prefieres mantenerlo: $profile->delete();
                // Si quieres borrado duro del perfil:
                $profile->delete();
            }

            // 3) Detach pivotes (ej. comunas)
            try {
                $user->communes()->detach();
            } catch (\Throwable $e) {
            }

            // 4) Soft delete del usuario (conserva el registro para auditoría)
            $user->save();   // guarda 'deleted_by'
            $user->delete(); // setea 'deleted_at'
        });

        return redirect()->route('admin.users.index')->with('status', 'Usuario eliminado correctamente.');
    }
    public function exportPdf(Request $request)
    {
        $user = $request->user();
        $user->load(['postulantProfile.comuna', 'postulantProfile.areaDesempeno', 'communes']);
        $profile = $user->postulantProfile;

        if (!$profile) {
            return back()->with('warning', 'Completa tu perfil antes de descargar el PDF.');
        }

        // --- RUT formateado: sin puntos, con guion ---
        $rutRaw = (string)($user->rut ?? '');
        $rutSan = strtoupper(preg_replace('/[^0-9Kk]/', '', $rutRaw)); // deja solo dígitos y K
        if ($rutSan !== '') {
            $dv     = substr($rutSan, -1);
            $cuerpo = substr($rutSan, 0, -1);
            $rutFmt = $cuerpo . '-' . $dv; // ej: 12345678-K
        } else {
            $rutFmt = 'ID' . $user->id;
        }

        // Región legible
        $regiones   = config('chile.regiones', []);
        $regionName = $profile->region_code ? ($regiones[$profile->region_code] ?? $profile->region_code) : '';

        // --- Nacionalidad + bandera (data URI) ---
        $nationalities = collect(config('nacionalidades', []));
        $val   = (string) ($profile->nacionalidad ?? '');
        $match = $nationalities->first(function ($n) use ($val) {
            return strcasecmp($n['value'] ?? '', $val) === 0
                || strcasecmp($n['iso'] ?? '', $val) === 0
                || strcasecmp($n['abbr'] ?? '', $val) === 0
                || strcasecmp($n['name'] ?? '', $val) === 0;
        });
        $nacName = $match['name'] ?? ($val ?: null);
        $iso2    = strtolower($match['iso'] ?? $match['value'] ?? '');

        $flagDataUrl = null;
        if ($iso2) {
            $candidates = [
                public_path("flags-svg/{$iso2}.svg"),
                public_path("flags/{$iso2}.png"),
            ];
            foreach ($candidates as $p) {
                if (is_file($p)) {
                    $mime = str_ends_with(strtolower($p), '.svg') ? 'image/svg+xml' : 'image/png';
                    $flagDataUrl = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($p));
                    break;
                }
            }
        }


        // Foto miniatura absoluta (si existe)
        $fotoThumbAbs = null;
        if (!empty($profile->foto_thumb_path) && Storage::disk('public')->exists($profile->foto_thumb_path)) {
            $fotoThumbAbs = Storage::disk('public')->path($profile->foto_thumb_path);
        }

        // Paleta/marca para la plantilla PDF.
        $brand = PdfBranding::profileBrand();

        $data = [
            'user'         => $user,
            'profile'      => $profile,
            'rutFmt'       => $rutFmt,
            'regionName'   => $regionName,
            'communes'     => $user->communes,
            'brand'        => $brand,
            'fotoThumbAbs' => $fotoThumbAbs,
            'generatedAt'  => now(),
            'nacName'     => $nacName,
            'flagDataUrl' => $flagDataUrl,
        ];

        $pdf = Pdf::loadView('pdf.profile', $data)
            ->setPaper('letter', 'portrait');

        // Nombre de archivo: PERFIL_{RUT}.pdf (sanitizado para FS)
        $fileRut = preg_replace('/[^0-9Kk-]/', '', $rutFmt); // mantiene dígitos, K y guion
        $filename = "PERFIL_{$fileRut}.pdf";

        return $pdf->download($filename);
    }

    public function ajaxAreasDesempeno(Request $request)
    {
        $term = (string) $request->query('term', '');
        $estamento = (string) $request->query('estamento', '');

        $q = AreaDesempeno::query()
            ->select(['id', 'nombre']);

        // Ajusta este filtro a tu esquema real:
        if ($estamento !== '') {
            $q->where('estamento', $estamento); // si tu columna es string 'estamento'
            // o si tienes estamento_id:
            // $map = ['docente' => 1, 'asistente' => 2, 'directivo' => 3];
            // if (isset($map[$estamento])) $q->where('estamento_id', $map[$estamento]);
        }

        if ($term !== '') {
            $q->where('nombre', 'like', "%{$term}%");
        }

        $results = $q->orderBy('nombre')
            ->get()
            ->map(fn($a) => ['id' => $a->id, 'text' => $a->nombre])
            ->values();

        return response()->json(['results' => $results]);
    }
    // Al final de la clase PostulantProfileController (antes del cierre de la clase)
    private function rutDv(string $numberOnly): string
    {
        $numberOnly = preg_replace('/\D/', '', $numberOnly);
        $s = 1;
        $m = 0;
        for ($i = strlen($numberOnly) - 1; $i >= 0; $i--) {
            $s = ($s + (int)$numberOnly[$i] * (9 - ($m++ % 6))) % 11;
        }
        // Resultado estándar chileno
        return $s ? (string)($s - 1) : 'K';
    }

    private function formatRut(?string $rut, bool $withDots = true): string
    {
        $rut = strtoupper(trim((string)$rut));
        if ($rut === '') return '';

        // Quita puntos/espacios
        $rut = str_replace(['.', ' '], '', $rut);

        $num = '';
        $dv  = '';

        if (str_contains($rut, '-')) {
            [$num, $dv] = explode('-', $rut, 2);
            $num = preg_replace('/\D/', '', $num);
            $dv  = preg_replace('/[^0-9K]/i', '', strtoupper($dv));
        } else {
            // Si viene todo junto, intenta separar; si no hay DV, lo calculamos
            if (preg_match('/^(\d+)([0-9K])$/i', $rut, $m)) {
                $num = $m[1];
                $dv  = strtoupper($m[2]);
            } else {
                $num = preg_replace('/\D/', '', $rut);
                $dv  = $this->rutDv($num);
            }
        }

        if ($withDots) {
            // Inserta puntos cada 3 desde la derecha
            $rev = strrev($num);
            $rev = implode('.', str_split($rev, 3));
            $num = strrev($rev);
        }

        return $num . '-' . $dv;
    }

    private function nacionalidades(): array
    {
        // Emojis de banderas (funciona sin flag-icons). Puedes ampliar el listado.
        return [
            'Chile' => '🇨🇱 Chile',
            'Argentina' => '🇦🇷 Argentina',
            'Perú' => '🇵🇪 Perú',
            'Bolivia' => '🇧🇴 Bolivia',
            'Brasil' => '🇧🇷 Brasil',
            'Uruguay' => '🇺🇾 Uruguay',
            'Paraguay' => '🇵🇾 Paraguay',
            'Colombia' => '🇨🇴 Colombia',
            'Venezuela' => '🇻🇪 Venezuela',
            'Ecuador' => '🇪🇨 Ecuador',
            'México' => '🇲🇽 México',
            'España' => '🇪🇸 España',
            'Estados Unidos' => '🇺🇸 Estados Unidos',
            'Italia' => '🇮🇹 Italia',
            'Francia' => '🇫🇷 Francia',
            'Alemania' => '🇩🇪 Alemania',
            'China' => '🇨🇳 China',
            'Japón' => '🇯🇵 Japón',
            'Corea del Sur' => '🇰🇷 Corea del Sur',
        ];
    }
}
