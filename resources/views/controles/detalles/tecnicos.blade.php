{{-- De qué lado falta el DT. --}}
@if(!$fila->tiene_dt_local)
    <span class="ctrl-chip mal">falta el DT de {{ $fila->equipo_local_nombre }}</span>
@endif
@if(!$fila->tiene_dt_visitante)
    <span class="ctrl-chip mal">falta el DT de {{ $fila->equipo_visitante_nombre }}</span>
@endif
