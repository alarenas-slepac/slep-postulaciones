@php
    $title = 'Admisión Escolar ' . config('admision.anio', 2027);
    $metaDescription = 'La nueva vitrina de establecimientos del SLEP Andalién Costa estará disponible próximamente.';
@endphp
@extends('layouts.admision-public')

@section('content')
<section class="ae-coming-soon">
    <div class="ae-container">
        <div class="ae-coming-soon__card">
            <div class="ae-coming-soon__mark" aria-hidden="true">A</div>
            <span class="ae-eyebrow">Admisión Escolar {{ config('admision.anio', 2027) }}</span>
            <h1>Estamos preparando una nueva forma de conocer nuestros establecimientos.</h1>
            <p class="ae-long-text">Muy pronto podrás explorar sellos educativos, niveles de enseñanza, equipos directivos, galerías y canales oficiales de cada comunidad educativa.</p>
            <div class="ae-hero__actions" style="justify-content:center;">
                <a class="ae-button ae-button--primary" href="{{ config('brand.org_url') }}" target="_blank" rel="noopener noreferrer">Visitar sitio institucional ↗</a>
                <a class="ae-button ae-button--outline" href="{{ config('admision.sae_url') }}" target="_blank" rel="noopener noreferrer">Sistema de Admisión Escolar ↗</a>
            </div>
        </div>
    </div>
</section>
@endsection
