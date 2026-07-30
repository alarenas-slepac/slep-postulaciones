@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="m-0">Nuevo establecimiento</h3>
    </div>

    @include('admin.establecimientos._form', [
        'item' => $item,
        'action' => route('admin.establecimientos.store'),
        'method' => null,
    ])
@endsection
