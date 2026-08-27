{{-- El arquero cargado contra el que estaba en cancha en ese minuto. --}}
@if(isset($fila->minuto) && $fila->minuto !== null)
    <span class="ctrl-chip neutro">minuto {{ $fila->minuto }}</span>
@endif
<span class="ctrl-chip mal">cargado: {{ $fila->arquero_cargado_nombre ?? '—' }}</span>
@if(!empty($fila->arquero_nombre))
    <span class="ctrl-chip ok">debería ser: {{ $fila->arquero_nombre }}</span>
@else
    <span class="ctrl-motivo">{{ $fila->motivo ?? 'No se pudo determinar el arquero.' }}</span>
@endif

{{-- Cómo llegó a esa conclusión. Sin esto hay que adivinar leyendo la base:
     casi siempre el control no se equivoca, le falta un minuto en un dato. --}}
@if(!empty($fila->traza_txt))
    <div class="ctrl-traza">{{ implode(' · ', $fila->traza_txt) }}</div>
@endif
