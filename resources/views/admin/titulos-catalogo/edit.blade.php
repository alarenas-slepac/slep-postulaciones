@extends('layouts.app')
@section('content')
<div class="container py-4">
    <h2 class="h4 mb-3">Editar título</h2>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('admin.titulos-catalogo.update', $item) }}">@method('PUT') @include('admin.titulos-catalogo._form')</form>
    </div></div>
</div>
@endsection
