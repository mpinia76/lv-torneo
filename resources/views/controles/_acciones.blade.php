{{--
    Los botones de cada fila. Cada chequeo declara en `Controles::definiciones()`
    qué acciones le sirven; acá se resuelven a la ruta que ya existía.
--}}
@php
    $partido = $fila->id;
    $mapa = [
        'alineaciones' => ['Alineaciones', 'ctrl-b-verde', route('alineaciones.index', ['partidoId' => $partido])],
        'goles'        => ['Goles',        'ctrl-b-azul',  route('goles.index', ['partidoId' => $partido, 'totalGoles' => (int) $fila->golesl + (int) $fila->golesv])],
        'tarjetas'     => ['Tarjetas',     'ctrl-b-azul',  route('tarjetas.index', ['partidoId' => $partido])],
        'cambios'      => ['Cambios',      'ctrl-b-azul',  route('cambios.index', ['partidoId' => $partido])],
        'penales'      => ['Penales',      'ctrl-b-azul',  route('penales.index', ['partidoId' => $partido])],
        'jueces'       => ['Jueces',       'ctrl-b-azul',  route('partidos.arbitros', ['partidoId' => $partido])],
        // Marcar el partido como excepción: con incidencia deja de aparecer
        // en los controles.
        'incidencia'   => ['Incidencia',   'ctrl-b-gris',  route('incidencias.create', ['torneoId' => $fila->torneo_id, 'partidoId' => $partido])],
    ];
@endphp

@foreach($def['acciones'] as $accion)
    @if(isset($mapa[$accion]))
        <a href="{{ $mapa[$accion][2] }}" class="{{ $mapa[$accion][1] }}">{{ $mapa[$accion][0] }}</a>
    @endif
@endforeach
