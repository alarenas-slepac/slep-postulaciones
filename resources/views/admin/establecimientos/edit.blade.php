@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h3 class="m-0">Editar establecimiento</h3>
    </div>

    @include('admin.establecimientos._form', [
        'item' => $item,
        'action' => route('admin.establecimientos.update', $item),
        'method' => 'PUT',
    ])
@endsection
