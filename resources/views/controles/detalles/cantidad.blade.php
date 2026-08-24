{{-- Repetidos: cuántas veces está cargado y de qué tipo o minuto. --}}
<span class="ctrl-chip mal">x{{ $fila->cantidad }}</span>
@if(!empty($fila->tipo))
    <span class="ctrl-chip neutro">{{ $fila->tipo }}</span>
@endif
@if(isset($fila->minuto) && $fila->minuto !== null)
    <span class="ctrl-sub">minuto {{ $fila->minuto }}</span>
@endif
