<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DeclaracionSostenedor extends Model
{
    public function tituloCatalogo()
    {
        return $this->belongsTo(TituloCatalogo::class, 'titulo_catalogo_id');
    }

    public function funcionCatalogo()
    {
        return $this->belongsTo(FuncionCatalogo::class, 'funcion_catalogo_id');
    }

    public function institucionCatalogo()
    {
        return $this->belongsTo(InstitucionCatalogo::class, 'institucion_catalogo_id');
    }

    protected $table = 'declaracion_sostenedores';



    public function isDocente(): bool
    {
        return mb_strtoupper((string) $this->estamento) === 'DOCENTE';
    }

    public function isAsistente(): bool
    {
        return mb_strtoupper((string) $this->estamento) === 'ASISTENTE';
    }

    public function hasNivelSeleccionado(): bool
    {
        return (bool) $this->educacion_parvularia
            || (bool) $this->ensenanza_basica
            || (bool) $this->ensenanza_media;
    }

    public function tipoTituloEsNinguno(): bool
    {
        return $this->isAsistente() && (string) ($this->tipo_titulo ?? '') === 'Ninguno';
    }

    public function requiereAntecedentesPorTipoTitulo(): bool
    {
        return $this->isAsistente();
    }

    public function requiereCertificadoAntecedentesParaConfirmacion(): bool
    {
        return $this->isDocente() || $this->isAsistente();
    }

    public function requiereCertificadoTituloParaConfirmacion(): bool
    {
        if ($this->isDocente()) {
            return true;
        }

        return $this->isAsistente() && !$this->tipoTituloEsNinguno();
    }

    public function tieneDatosTituloRegistrados(): bool
    {
        return filled($this->nombre_titulo)
            || !empty($this->titulo_catalogo_id)
            || filled($this->institucion_educacional)
            || !empty($this->institucion_catalogo_id)
            || filled($this->fecha_titulacion)
            || filled($this->pais_titulo)
            || filled($this->certificado_titulo);
    }

    public function requiereCertificadoTituloParaEstadistica(): bool
    {
        if ($this->isDocente()) {
            return $this->tieneDatosTituloRegistrados();
        }

        return $this->requiereCertificadoTituloParaConfirmacion();
    }

    public function documentosRequeridosParaEstadisticaCount(): int
    {
        $total = 0;

        if ($this->requiereCertificadoAntecedentesParaConfirmacion()) {
            $total++;
        }

        if ($this->requiereCertificadoTituloParaEstadistica()) {
            $total++;
        }

        return $total;
    }

    public function documentosCargadosParaEstadisticaCount(): int
    {
        $total = 0;

        if ($this->requiereCertificadoAntecedentesParaConfirmacion() && filled($this->certificado_antecedentes)) {
            $total++;
        }

        if ($this->requiereCertificadoTituloParaEstadistica() && filled($this->certificado_titulo)) {
            $total++;
        }

        return $total;
    }

    public function tieneFuncionSeleccionada(): bool
    {
        return !empty($this->funcion_catalogo_id);
    }

    public function funcionEsOtro(): bool
    {
        $nombre = $this->relationLoaded('funcionCatalogo') && $this->funcionCatalogo
            ? (string) $this->funcionCatalogo->nombre
            : '';

        return $this->normalizeComparableText($nombre) === 'otro';
    }

    public function tieneTextoFuncionOtro(): bool
    {
        $nombreFuncion = $this->normalizeComparableText((string) ($this->nombre_funcion ?? ''));

        return $nombreFuncion !== null && $nombreFuncion !== 'otro';
    }

    protected function normalizeComparableText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return (string) Str::of($value)
            ->lower()
            ->ascii()
            ->replace(['_', '-', '.', 'º', '°', '#'], ' ')
            ->squish();
    }
    protected $fillable = [
        'numero','rbd','rut','nombres','apellido_paterno','apellido_materno',
        'horas_contratadas','educacion_parvularia','ensenanza_basica','ensenanza_media',
        'certificado_titulo','certificado_antecedentes',
        'nombre_titulo','titulo_catalogo_id','institucion_catalogo_id','funcion_catalogo_id','nombre_funcion','tipo_titulo','institucion_educacional','fecha_titulacion','pais_titulo','estamento',
        'observacion_funcionario','confirma_registro'
    ];
}

