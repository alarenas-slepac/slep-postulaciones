@extends('layouts.app')

@section('content')
    <h1 class="h4 mb-3">Nuevo valor hora AAEE</h1>

    <div class="card card-body">
        <form method="POST" action="{{ route('admin.aaee-valores-hora.store') }}">
            @include('admin.aaee-valores-hora._form')
        </form>
    </div>
@endsection
