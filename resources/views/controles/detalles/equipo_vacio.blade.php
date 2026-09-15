{{-- Qué lado quedó sin equipo, y el link a la pantalla donde se completa.

     El nombre viene del LEFT JOIN de `Controles::base(..., true)`: si es null
     es porque `equipol_id`/`equipov_id` no apunta a ninguna fila de `equipos`
     —está en 0, o el equipo se borró después de cargar el partido—. --}}
@if(!$fila->equipo_local_nombre)
    <span class="ctrl-chip mal">falta el equipo local{{ $fila->equipol_id ? ' (#'.$fila->equipol_id.' no existe)' : '' }}</span>
@endif
@if(!$fila->equipo_visitante_nombre)
    <span class="ctrl-chip mal">falta el visitante{{ $fila->equipov_id ? ' (#'.$fila->equipov_id.' no existe)' : '' }}</span>
@endif
<span class="ctrl-sub">
    <a href="{{ route('import_detalles.equipos_vacios') }}" target="_blank" rel="noopener">completarlo</a>
</span>
