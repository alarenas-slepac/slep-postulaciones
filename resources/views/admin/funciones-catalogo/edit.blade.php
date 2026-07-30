@extends('layouts.app')
@section('content')
<div class="container py-4">
    <h2 class="h4 mb-3">Editar función</h2>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('admin.funciones-catalogo.update', $item) }}">@method('PUT') @include('admin.funciones-catalogo._form')</form>
    </div></div>
</div>
@endsection
