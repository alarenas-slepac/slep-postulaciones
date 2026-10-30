@if (session('success'))
    <div class="slep-record-state slep-record-state--success mb-4" role="status">{{ session('success') }}</div>
@endif
@if ($errors->any())
    <div class="slep-listing__notice slep-listing__notice--danger" role="alert" tabindex="-1" data-mat-errors>
        <div class="fw-bold mb-2">Revise los datos antes de continuar</div>
        <ul class="mb-0 ps-3">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif
