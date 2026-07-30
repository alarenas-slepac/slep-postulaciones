<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentType extends Model
{
    protected $fillable = [
        'slug',
        'label',
        'required_for',
        'conditions',
        'template_path',
        'sort_order',
    ];

    protected $casts = [
        'conditions' => 'array',
    ];

    public function userDocuments()
    {
        return $this->hasMany(UserDocument::class);
    }

    /**
     * Determina si este tipo es requerido para un usuario dado
     * en base a estamento, área, cargo, género, nivel y mención.
     */
    public function isRequiredForUser(\App\Models\User $user): bool
    {
        return $this->matchesUserProfile($user, allowOptional: false);
    }

    /**
     * Determina si este tipo debe mostrarse/permitirse para el usuario,
     * incluyendo documentos opcionales condicionados por el perfil.
     */
    public function isVisibleForUser(\App\Models\User $user): bool
    {
        return $this->matchesUserProfile($user, allowOptional: true);
    }

    protected function matchesUserProfile(\App\Models\User $user, bool $allowOptional): bool
    {
        $profile = $user->postulantProfile;
        $est     = (string) ($profile->estamento ?? '');
        $area    = (string) ($profile->area_desempeno_nombre ?? '');
        $cargo   = (string) ($profile->cargos_funcion ?? '');
        $genero  = (string) ($profile->genero ?? '');
        $nivel   = (string) ($profile->nivel_estudios ?? '');
        $mencion = (string) ($profile->mencion ?? '');
        $aniosExperiencia = (int) ($profile->anios_experiencia ?? 0);

        // Reglas especiales por documento (definidas funcionalmente por el negocio)
        // Niveles considerados como "Enseñanza Media" (incluye laboral y TP).
        $nivelNorm = trim($nivel);
        $nivelesMedia = ['Enseñanza Media', 'Enseñanza Media Laboral', 'Enseñanza Media TP'];
        $esNivelMedia = in_array($nivelNorm, $nivelesMedia, true);

        // Licencia de Enseñanza Media: solo aplica a asistentes con nivel de estudio "media".
        if ($this->slug === 'licencia_media') {
            return $est === 'asistente' && $esNivelMedia;
        }

        // Título profesional o técnico: requerido para todos excepto asistentes con nivel "media".
        if ($this->slug === 'titulo') {
            if ($est === 'asistente' && $esNivelMedia) {
                return false;
            }
        }

        // required_for rápido
        if (in_array($this->required_for, ['docente', 'asistente', 'both'], true)) {
            if ($this->required_for === 'docente' && $est !== 'docente') return false;
            if ($this->required_for === 'asistente' && $est !== 'asistente') return false;
        }

        // Condicionales
        $c = $this->conditions ?? [];
        // require_mencion (solo si Docente con área que exige mención, ya calculaste en UI/Request)
        if (!empty($c['require_mencion']) && $c['require_mencion'] === true && $mencion === '') {
            return false;
        }
        // require_area_in
        if (!empty($c['require_area_in']) && is_array($c['require_area_in'])) {
            if (!in_array($area, $c['require_area_in'], true)) return false;
        }
        // require_cargo_in
        if (!empty($c['require_cargo_in']) && is_array($c['require_cargo_in'])) {
            if (!in_array($cargo, $c['require_cargo_in'], true)) return false;
        }
        // require_genero_in
        if (!empty($c['require_genero_in']) && is_array($c['require_genero_in'])) {
            if (!in_array($genero, $c['require_genero_in'], true)) return false;
        }
        // require_nivel_in
        if (!empty($c['require_nivel_in']) && is_array($c['require_nivel_in'])) {
            if (!in_array($nivel, $c['require_nivel_in'], true)) return false;
        }

        // Documento opcional visible desde cierto mínimo de experiencia.
        if (array_key_exists('optional_min_anios_experiencia', $c)) {
            $minAnios = max(0, (int) $c['optional_min_anios_experiencia']);
            if ($aniosExperiencia < $minAnios) {
                return false;
            }
            if (!$allowOptional) {
                return false;
            }
        }

        // Si pasa todo lo anterior, se considera requerido
        return true;
    }
}
