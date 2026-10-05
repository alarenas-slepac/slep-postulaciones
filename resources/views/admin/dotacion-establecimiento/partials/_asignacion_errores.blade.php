@if ($esFormularioFallido($item, $tipoFormulario ?? null))
    <div class="alert alert-danger small mb-1" role="alert" data-dotacion-form-errors>
        <strong>Revise esta asignación. Se conservaron los datos ingresados.</strong>
        @foreach ($errors->getMessages() as $campo => $mensajes)
            <div data-dotacion-field-error="{{ $campo }}">{{ implode(' ', $mensajes) }}</div>
        @endforeach
    </div>
@endif
