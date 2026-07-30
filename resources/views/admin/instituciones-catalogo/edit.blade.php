@extends('layouts.app')
@section('content')
<div class="container py-4">
    <h2 class="h4 mb-3">Editar institución</h2>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('admin.instituciones-catalogo.update', $item) }}">
            @method('PUT')
            @include('admin.instituciones-catalogo._form')
        </form>
    </div></div>
</div>
@endsection
