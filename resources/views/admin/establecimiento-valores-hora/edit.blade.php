@extends('layouts.app')

@section('content')
    <h1 class="h4 mb-3">Editar valor hora por establecimiento</h1>

    <div class="card card-body">
        <form method="POST" action="{{ route('admin.establecimiento-valores-hora.update', $item) }}">
            @method('PUT')
            @include('admin.establecimiento-valores-hora._form')
        </form>
    </div>
@endsection
