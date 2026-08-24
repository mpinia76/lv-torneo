{{-- Goles cargados contra los que dice el resultado. --}}
<span class="ctrl-chip mal">{{ $fila->goles_cargados }} cargados</span>
<span class="ctrl-chip neutro">{{ (int) $fila->golesl + (int) $fila->golesv }} según el resultado</span>
