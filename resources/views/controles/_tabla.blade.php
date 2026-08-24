{{--
    La tabla de resultados de cualquier chequeo.

    Antes cada pestaña de cada pantalla repetía estas mismas 40 líneas (eran
    catorce copias en total, con los escudos y el marcador copiados a mano en
    cada una). Ahora hay una sola: lo único que cambia entre chequeos es si se
    muestra la columna del jugador y qué partial de `detalles/` se usa.
--}}

@if($filas->total() === 0)
    <div class="ctrl-vacio">
        @if($filtros['year'] || $filtros['torneo'] || $filtros['q'])
            No hay nada para corregir en este control con los filtros puestos.
        @else
            No hay nada para corregir en este control.
        @endif
    </div>
@else
    <div style="overflow-x:auto">
        <table class="ctrl-tabla">
            <thead>
            <tr>
                <th>Torneo</th>
                <th style="text-align:center">Partido</th>
                @if(!empty($def['jugador']))
                    <th>{{ $def['columna_jugador'] ?? 'Jugador' }}</th>
                @endif
                @if(!empty($def['detalle']))
                    <th>Detalle</th>
                @endif
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach($filas as $fila)
                <tr>
                    <td>
                        <span class="ctrl-torneo">
                            <a href="{{ route('fechas.show', $fila->fecha_id) }}">{{ $fila->torneo }} {{ $fila->year }}</a>
                        </span>
                        <span class="ctrl-sub">
                            {{ is_numeric($fila->fecha) ? 'Fecha '.$fila->fecha : $fila->fecha }}
                            @if($fila->dia) · {{ date('d/m/Y', strtotime($fila->dia)) }} @endif
                        </span>
                    </td>

                    <td class="ctrl-partido" style="text-align:center">
                        <span class="ctrl-equipo local">
                            {{ $fila->equipo_local_nombre }}
                            @if($fila->equipo_local_escudo)
                                <img src="{{ url('images/'.$fila->equipo_local_escudo) }}" alt="">
                            @endif
                        </span>
                        <span class="ctrl-marcador">
                            {{ $fila->golesl }}-{{ $fila->golesv }}
                            @if($fila->penalesl || $fila->penalesv)
                                <span class="ctrl-penales">({{ $fila->penalesl }}-{{ $fila->penalesv }})</span>
                            @endif
                        </span>
                        <span class="ctrl-equipo visita">
                            @if($fila->equipo_visitante_escudo)
                                <img src="{{ url('images/'.$fila->equipo_visitante_escudo) }}" alt="">
                            @endif
                            {{ $fila->equipo_visitante_nombre }}
                        </span>
                    </td>

                    @if(!empty($def['jugador']))
                        <td class="ctrl-jugador">
                            <img src="{{ $fila->jugador_foto ? url('images/'.$fila->jugador_foto) : url('images/sin_foto.png') }}" alt="">
                            {{ trim($fila->jugador_apellido.', '.$fila->jugador_nombre, ', ') }}
                        </td>
                    @endif

                    @if(!empty($def['detalle']))
                        <td>@include('controles.detalles.'.$def['detalle'], ['fila' => $fila])</td>
                    @endif

                    <td class="ctrl-botones">@include('controles._acciones', ['fila' => $fila, 'def' => $def])</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="row ctrl-pie">
        <div class="col-md-9">{{ $filas->links() }}</div>
        <div class="col-md-3 ctrl-resumen" style="text-align:right">
            <strong>{{ number_format($filas->total(), 0, ',', '.') }}</strong>
            {{ $filas->total() === 1 ? 'caso' : 'casos' }}
        </div>
    </div>
@endif
