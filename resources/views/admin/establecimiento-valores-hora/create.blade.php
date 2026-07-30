@extends('layouts.app')

@section('content')
    <h1 class="h4 mb-3">Nuevo valor hora por establecimiento</h1>

    <div class="card card-body">
        <form method="POST" action="{{ route('admin.establecimiento-valores-hora.store') }}">
            @include('admin.establecimiento-valores-hora._form')
        </form>
    </div>
@endsection
