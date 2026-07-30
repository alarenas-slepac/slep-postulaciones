@extends('layouts.app')
@section('content')
<div class="container py-4">
    <h2 class="h4 mb-3">Editar oferta laboral</h2>
    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('gestion.bolsa-trabajo.update', $item) }}" enctype="multipart/form-data">
                @method('PUT')
                @include('gestion.bolsa-trabajo._form')
            </form>
        </div>
    </div>
</div>
@endsection
