@extends('layouts.app')
@section('content')
<div class="container py-4">
    <h2 class="h4 mb-3">Nuevo título</h2>
    <div class="card"><div class="card-body">
        <form method="POST" action="{{ route('admin.titulos-catalogo.store') }}">@include('admin.titulos-catalogo._form')</form>
    </div></div>
</div>
@endsection
