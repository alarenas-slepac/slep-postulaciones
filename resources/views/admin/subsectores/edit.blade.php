@extends('layouts.app')

@section('content')
    <div class="container">
        <h3 class="mb-3">Editar subsector</h3>

        @if ($errors->any())
            <div class="alert alert-danger"><strong>Corrige los siguientes errores:</strong></div>
        @endif

        <form method="POST" action="{{ route('admin.subsectores.update', $subsector) }}">
            @method('PUT')
            @include('admin.subsectores._form')
        </form>
    </div>
@endsection
