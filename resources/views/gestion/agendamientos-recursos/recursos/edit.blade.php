@extends('layouts.app')
@section('content')
<div class="container-fluid py-3"><div class="card border-0 shadow-sm rounded-4"><div class="card-header bg-white border-0 p-4"><h1 class="h4 mb-0">Editar sala/recurso</h1></div><div class="card-body p-4"><form method="POST" action="{{ route('gestion.agendamientos-recursos.recursos.update', $recurso) }}">@method('PUT')@include('gestion.agendamientos-recursos.recursos.form')</form></div></div></div>
@endsection
