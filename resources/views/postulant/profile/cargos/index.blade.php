@extends('layouts.app')

@section('content')
<x-postulant-page title="Cargos a los que quiero postular" icon="bi-briefcase" description="Registra las áreas y títulos con los que deseas postular. Los antecedentes personales y la biblioteca de documentos se comparten.">
    <x-slot:actions>
        <a href="{{ route('postulant.profile.edit') }}" class="slep-listing__button slep-listing__button--secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Mi perfil</a>
        @if (filled($profile?->estamento))
            <a href="{{ route('postulant.profile.cargos.create') }}" class="slep-listing__button slep-listing__button--primary"><i class="bi bi-plus-lg" aria-hidden="true"></i> Agregar cargo</a>
        @endif
    </x-slot:actions>
    <section class="slep-listing__panel" aria-labelledby="cargos-heading">
        <div class="slep-listing__panel-header"><h2 id="cargos-heading">Mis perfiles laborales</h2></div>
        @if (filled($profile?->estamento))
            <ul class="list-group list-group-flush mb-0">
                <li class="list-group-item px-3 px-md-4 py-3 d-flex flex-wrap justify-content-between gap-3 align-items-center">
                    <div class="text-break">
                        <span class="slep-status-pill slep-status-pill--info mb-2">Principal</span>
                        <div class="fw-semibold">{{ \App\Support\PostulantJobProfiles::label($profile) }}</div>
                    </div>
                    <a href="{{ route('postulant.profile.edit') }}#anchor-estamento" class="slep-listing__button slep-listing__button--secondary">Editar cargo principal</a>
                </li>
                @foreach ($profile->jobProfiles as $job)
                    <li class="list-group-item px-3 px-md-4 py-3 d-flex flex-wrap justify-content-between gap-3 align-items-center">
                        <div class="text-break">
                            <span class="slep-status-pill slep-status-pill--{{ $job->activo && $job->area_desempeno_id ? 'success' : 'neutral' }} mb-2">{{ $job->area_desempeno_id ? ($job->activo ? 'Activo' : 'Inactivo') : 'Área por actualizar' }}</span>
                            <div class="fw-semibold">{{ \App\Support\PostulantJobProfiles::label($job) }}</div>
                        </div>
                        <a href="{{ route('postulant.profile.cargos.edit', $job) }}" class="slep-listing__button slep-listing__button--secondary" aria-label="Editar {{ \App\Support\PostulantJobProfiles::label($job) }}">Editar cargo</a>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="slep-listing__empty">
                <i class="bi bi-person-vcard" aria-hidden="true"></i>
                <h3>Completa tu perfil principal</h3>
                <a class="slep-listing__button slep-listing__button--primary" href="{{ route('postulant.profile.edit') }}">Completar mi perfil</a>
            </div>
        @endif
    </section>
</x-postulant-page>
@endsection
