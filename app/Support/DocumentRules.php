<?php

namespace App\Support;

use App\Models\DocumentType;
use App\Models\User;

class DocumentRules
{
    /**
     * @param \Illuminate\Support\Collection<int,\App\Models\DocumentType> $types
     * @return \Illuminate\Support\Collection<int,\App\Models\DocumentType>
     */
    protected static function filterTypesForUser(User $user, $types, string $method)
    {
        // Datos del perfil para condicionales "OR" especiales
        $profile = $user->postulantProfile;
        $area    = (string) ($profile->area_desempeno_nombre ?? '');
        $cargo   = (string) ($profile->cargos_funcion ?? '');

        return $types->filter(function (DocumentType $t) use ($user, $area, $cargo, $method) {
            // Soporte de "OR" opcional en condiciones
            $c = $t->conditions ?? [];

            // Si define or_require_cargo_in, aplica "OR" con área (si existe) u otros
            if (!empty($c['or_require_cargo_in']) && is_array($c['or_require_cargo_in'])) {
                $okA = true;
                if (!empty($c['require_area_in']) && is_array($c['require_area_in'])) {
                    $okA = in_array($area, $c['require_area_in'], true);
                }
                $okB = in_array($cargo, $c['or_require_cargo_in'], true);

                if ($okA || $okB) {
                    // Fuerza a pasar las demás condiciones salvo conflicto de área/cargo ya resuelto.
                    $cc = $c;
                    unset($cc['require_area_in'], $cc['or_require_cargo_in']);
                    $temp = new DocumentType($t->toArray());
                    $temp->conditions = $cc;
                    return $temp->{$method}($user);
                }
                return false;
            }

            return $t->{$method}($user);
        })->values();
    }

    /**
     * Devuelve la lista de DocumentType REQUERIDOS para el usuario,
     * considerando 'required_for' y 'conditions'.
     */
    public static function requiredTypesFor(User $user)
    {
        $types = DocumentType::orderBy('sort_order')->get();
        return self::filterTypesForUser($user, $types, 'isRequiredForUser');
    }

    /**
     * Devuelve la lista de DocumentType visibles/permitidos para el usuario,
     * incluyendo documentos opcionales condicionados por el perfil.
     */
    public static function visibleTypesFor(User $user)
    {
        $types = DocumentType::orderBy('sort_order')->get();
        return self::filterTypesForUser($user, $types, 'isVisibleForUser');
    }

    /**
     * Igual que requiredTypesFor(), pero reutiliza un catálogo de DocumentType ya cargado
     * (evita múltiples consultas al DB en endpoints tipo AJAX).
     *
     * @param \Illuminate\Support\Collection<int,\App\Models\DocumentType> $types
     * @return \Illuminate\Support\Collection<int,\App\Models\DocumentType>
     */
    public static function requiredTypesFromCatalog(User $user, $types)
    {
        return self::filterTypesForUser($user, $types, 'isRequiredForUser');
    }

    /**
     * Igual que visibleTypesFor(), pero reutiliza un catálogo ya cargado.
     *
     * @param \Illuminate\Support\Collection<int,\App\Models\DocumentType> $types
     * @return \Illuminate\Support\Collection<int,\App\Models\DocumentType>
     */
    public static function visibleTypesFromCatalog(User $user, $types)
    {
        return self::filterTypesForUser($user, $types, 'isVisibleForUser');
    }

}
