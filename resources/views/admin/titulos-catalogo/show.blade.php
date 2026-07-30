@extends('layouts.app')
@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h4 mb-0">Ver título</h2><a class="btn btn-outline-secondary" href="{{ route('admin.titulos-catalogo.index') }}">Volver</a></div>
    <div class="card"><div class="card-body"><dl class="row mb-0"><dt class="col-sm-3">ID</dt><dd class="col-sm-9">{{ $item->id }}</dd><dt class="col-sm-3">Nombre</dt><dd class="col-sm-9">{{ $item->nombre }}</dd><dt class="col-sm-3">Creado</dt><dd class="col-sm-9">{{ optional($item->created_at)->format('d-m-Y H:i') }}</dd><dt class="col-sm-3">Actualizado</dt><dd class="col-sm-9">{{ optional($item->updated_at)->format('d-m-Y H:i') }}</dd></dl></div></div>
</div>
@endsection
