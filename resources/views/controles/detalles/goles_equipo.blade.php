{{-- Goles cargados de cada lado contra lo que dice el resultado. --}}
<span class="ctrl-chip {{ (int) $fila->goles_local_cargados === (int) $fila->golesl ? 'ok' : 'mal' }}">
    Local {{ $fila->goles_local_cargados }} de {{ (int) $fila->golesl }}
</span>
<span class="ctrl-chip {{ (int) $fila->goles_visitante_cargados === (int) $fila->golesv ? 'ok' : 'mal' }}">
    Visitante {{ $fila->goles_visitante_cargados }} de {{ (int) $fila->golesv }}
</span>
