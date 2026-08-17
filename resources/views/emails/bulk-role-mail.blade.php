<h2>{{ $mailSubject }}</h2>
<p>Hola {{ $recipientName ?: 'usuario/a' }},</p>
@foreach($messageLines as $line)
    @if(trim($line) === '')
        <br>
    @else
        <p>{{ $line }}</p>
    @endif
@endforeach
<hr>
<p><small>Mensaje enviado desde la plataforma institucional SLEP Andalién Costa.</small></p>
