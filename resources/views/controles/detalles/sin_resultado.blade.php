{{--
    Por qué este partido está en la lista y qué se puede hacer con él.

    Dos datos, y el segundo es el que decide:

      cuánto hace que pasó  - el de la semana pasada es un olvido reciente; el
                              de hace tres años probablemente nunca se jugó.
      qué tiene cargado     - con goles cargados y sin marcador, el resultado YA
                              está en la base (lo trajo el detalle y el partido
                              se quedó sin el número): ahí no hay que ir a
                              buscar nada a ningún lado. Con alineación y sin
                              goles, el partido se bajó pero quedó a medias. Sin
                              nada, es una fila del fixture y puede no haberse
                              jugado nunca.

    Las tres columnas las agrega `Controles::partidosSinResultado()`.
--}}
@php
    $dias = (int) $fila->dias_pasados;

    if ($dias < 45) {
        $hace = 'hace '.$dias.($dias === 1 ? ' día' : ' días');
    } elseif ($dias < 730) {
        $meses = (int) floor($dias / 30);
        $hace  = 'hace '.$meses.($meses === 1 ? ' mes' : ' meses');
    } else {
        $hace = 'hace '.((int) floor($dias / 365)).' años';
    }

    $goles      = (int) $fila->filas_goles;
    $alineacion = (int) $fila->filas_alineacion;
@endphp

<span class="ctrl-chip {{ $dias > 30 ? 'mal' : 'aviso' }}">{{ $hace }}</span>

@if($goles > 0)
    <span class="ctrl-chip mal">{{ $goles }} {{ $goles === 1 ? 'gol cargado' : 'goles cargados' }}</span>
@elseif($alineacion > 0)
    <span class="ctrl-chip aviso">con alineación</span>
@else
    <span class="ctrl-chip neutro">sin detalle</span>
@endif

<div class="ctrl-traza">
    @if($goles > 0)
        El resultado ya está en la base: tiene goles cargados y el partido sigue sin marcador.
        No hace falta llamar a Transfermarkt.
    @elseif($alineacion > 0)
        El partido se bajó (tiene alineación) pero el marcador nunca se escribió.
    @else
        Sólo la fila del fixture: no tiene alineación ni goles. Puede que no se haya jugado.
    @endif
</div>
