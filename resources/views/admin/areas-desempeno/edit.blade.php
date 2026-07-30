@extends('layouts.app')

@section('content')
    <h1 class="h4 mb-3">Editar área</h1>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('admin.areas-desempeno.update', $area) }}">
                @csrf
                @method('PUT')
                @include('admin.areas-desempeno._form', ['area' => $area])
            </form>
        </div>
    </div>
@endsection
