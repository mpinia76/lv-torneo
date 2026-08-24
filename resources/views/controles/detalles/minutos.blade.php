{{-- Minutos donde no coinciden los que entran con los que salen. --}}
<span class="ctrl-sub">minuto</span>
@foreach(explode(', ', (string) $fila->minutos) as $minuto)
    <span class="ctrl-chip mal">{{ $minuto }}</span>
@endforeach
