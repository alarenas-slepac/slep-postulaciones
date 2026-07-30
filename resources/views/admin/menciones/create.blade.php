@extends('layouts.app')

@section('content')
    <div class="container">
        <h3 class="mb-3">Nueva mención</h3>

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Corrige los siguientes errores:</strong>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.menciones.store') }}">
            @include('admin.menciones._form')
        </form>
    </div>
@endsection
