@extends('layouts.app')
@section('content')
<x-postulant-page title="Nueva oferta laboral" icon="bi-briefcase">
    <section class="slep-listing__panel">
        <div class="slep-listing__panel-header"><h2>Datos de la oferta</h2></div>
        <div class="slep-listing__filters">
            <form method="POST" action="{{ route('gestion.bolsa-trabajo.store') }}" enctype="multipart/form-data" data-slep-saving="Guardando…">
                @include('gestion.bolsa-trabajo._form')
            </form>
        </div>
    </section>
</x-postulant-page>
@endsection
