@if (session('status'))
    <div class="alert alert-success rounded-4" role="status"><i class="bi bi-check-circle me-2" aria-hidden="true"></i>{{ session('status') }}</div>
@endif
@if (session('warning'))
    <div class="alert alert-warning rounded-4" role="alert"><i class="bi bi-exclamation-circle me-2" aria-hidden="true"></i>{{ session('warning') }}</div>
@endif
@if ($errors->any() || session('error'))
    <div class="slep-listing__notice slep-listing__notice--danger" role="alert" tabindex="-1" data-slep-errors>
        <strong><i class="bi bi-exclamation-circle me-2" aria-hidden="true"></i>Revisa la información ingresada</strong>
        @if (session('error'))<p class="mb-0 mt-1">{{ session('error') }}</p>@endif
        @if ($errors->any())
            <ul class="mb-0 mt-1">
                @foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach
            </ul>
        @endif
    </div>
@endif
