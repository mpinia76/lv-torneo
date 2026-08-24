{{-- Cuántos titulares tiene cada lado; se marca en rojo el que no da 11. --}}
<span class="ctrl-chip {{ (int) $fila->titulares_local === 11 ? 'ok' : 'mal' }}">
    Local {{ $fila->titulares_local }}
</span>
<span class="ctrl-chip {{ (int) $fila->titulares_visitante === 11 ? 'ok' : 'mal' }}">
    Visitante {{ $fila->titulares_visitante }}
</span>
