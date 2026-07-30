@extends('layouts.app')

@section('content')
    <div class="row g-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h1 class="h4 mb-3">Bienvenido 👋</h1>
                    <p class="mb-2">
                        Front con <strong>Bootstrap 5</strong> via Vite está funcionando.
                    </p>

                    <hr>

                    <h2 class="h6">Banderas</h2>
                    <p class="mb-1">Por defecto usamos <strong>emoji</strong>:
                        <span class="ms-2">🇨🇱 🇦🇷 🇵🇪 🇧🇷 🇺🇸 🇪🇸</span>
                    </p>
                    <p class="mb-0">Si instalas <code>flag-icons</code>, puedes usar:
                        <span class="fi fi-cl ms-2" title="Chile"></span>
                        <span class="fi fi-ar ms-2" title="Argentina"></span>
                        <span class="fi fi-pe ms-2" title="Perú"></span>
                        <span class="fi fi-us ms-2" title="Estados Unidos"></span>
                        <span class="fi fi-es ms-2" title="España"></span>
                    </p>
                    <small class="text-muted">Nota: si no instalas la librería, esos <code>fi fi-xx</code> no se verán (los
                        emoji sí).</small>
                </div>
            </div>
        </div>
    </div>
@endsection
