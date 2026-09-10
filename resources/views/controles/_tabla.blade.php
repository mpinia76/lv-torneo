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
    @php
        // ── Qué filas pueden entrar en "Rehacer seleccionados" ─────────────
        //
        // Sólo las que YA tienen gameId anotado: el lote escribe sin vista
        // previa, y en un partido sin gameId "Rehacer" primero sale a buscarlo
        // a Transfermarkt y puede terminar ofreciendo candidatos para elegir.
        // Eso se decide de a uno, así que esas filas no llevan tilde y se
        // quedan con su botón "Rehacer" de siempre.
        //
        // Y un tilde por PARTIDO, no por fila: en los controles por jugador el
        // mismo partido aparece varias veces (dos goleadores, dos amonestados)
        // y rehacerlo dos veces son dos llamadas por el mismo dato.
        $conTilde  = [];
        $sinGameId = 0;
        foreach ($filas as $filaTilde) {
            if (!empty($filaTilde->tm_game_id)) {
                $conTilde[$filaTilde->id] = true;
            } else {
                $sinGameId++;
            }
        }
        $tildePuesto = [];
    @endphp

    @if(!empty($conTilde))
        <div class="ctrl-lote">
            <form method="POST" action="{{ route('controles.rehacer') }}" id="ctrl-lote-form">
                @csrf
                {{-- Los ids los junta el JS al enviar: los checkbox NO pueden
                     estar dentro de este form porque cada fila ya tiene el suyo
                     ("Sin datos en TM") y un form adentro de otro form no
                     existe en HTML — el navegador lo desarma y se pierde uno de
                     los dos. --}}
                <input type="hidden" name="ids" id="ctrl-lote-ids" value="">
                <label class="ctrl-lote-todos">
                    <input type="checkbox" id="ctrl-lote-todos">
                    Tildar los {{ count($conTilde) }} de esta página
                </label>
                <button type="submit" class="ctrl-lote-boton" id="ctrl-lote-boton" disabled
                        title="Vuelve a bajar el detalle de los partidos tildados desde Transfermarkt y lo escribe. Cuesta 1 llamada por partido.">Rehacer seleccionados</button>
                <span class="ctrl-lote-nota">
                    Baja y <b>escribe</b> el detalle de los tildados sin pasar por la vista previa: reemplaza
                    alineación, goles, tarjetas, cambios y árbitros. <b>1 llamada por partido</b> (más las fotos
                    de los jugadores nuevos), así que puede tardar un rato.
                    @if($sinGameId)
                        {{ $sinGameId === 1 ? 'Hay 1 fila sin gameId anotado' : 'Hay '.$sinGameId.' filas sin gameId anotado' }}:
                        esas no se pueden tildar y van de a una con su botón «Rehacer», que lo busca en Transfermarkt.
                    @endif
                </span>
            </form>
        </div>
    @endif

    <div style="overflow-x:auto">
        <table class="ctrl-tabla">
            <thead>
            <tr>
                @if(!empty($conTilde))
                    <th class="ctrl-tilde-celda">
                        <input type="checkbox" id="ctrl-lote-todos-th" title="Tildar todos los de esta página">
                    </th>
                @endif
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
                    @if(!empty($conTilde))
                        <td class="ctrl-tilde-celda">
                            @if(isset($conTilde[$fila->id]) && !isset($tildePuesto[$fila->id]))
                                @php $tildePuesto[$fila->id] = true; @endphp
                                <input type="checkbox" class="ctrl-tilde" value="{{ $fila->id }}"
                                       title="Rehacerle el detalle a este partido (gameId {{ $fila->tm_game_id }})">
                            @elseif(isset($conTilde[$fila->id]))
                                <span class="ctrl-tilde-repe" title="Este partido ya está tildado más arriba: se rehace una sola vez.">↑</span>
                            @else
                                <span class="ctrl-tilde-no" title="Sin gameId anotado: rehacelo de a uno con el botón «Rehacer», que lo busca en Transfermarkt.">·</span>
                            @endif
                        </td>
                    @endif
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

    @if(!empty($conTilde))
        {{-- Va acá y no en @section('bottom'): los elementos que maneja son los
             de esta partial, y así la funcionalidad entera queda en un archivo.
             Se ejecuta después del <table>, así que los checkbox ya existen. --}}
        <script>
            (function () {
                var form = document.getElementById('ctrl-lote-form');
                if (!form) { return; }

                var campo  = document.getElementById('ctrl-lote-ids');
                var boton  = document.getElementById('ctrl-lote-boton');
                var tildes = Array.prototype.slice.call(document.querySelectorAll('.ctrl-tilde'));
                // Los dos "tildar todos": el de la barra y el del encabezado.
                var todos  = [document.getElementById('ctrl-lote-todos'),
                              document.getElementById('ctrl-lote-todos-th')].filter(Boolean);

                function elegidos() {
                    return tildes.filter(function (t) { return t.checked; });
                }

                function refrescar() {
                    var n = elegidos().length;
                    boton.disabled = n === 0;
                    boton.textContent = n === 0
                        ? 'Rehacer seleccionados'
                        : 'Rehacer ' + n + (n === 1 ? ' seleccionado' : ' seleccionados');
                    todos.forEach(function (t) { t.checked = n > 0 && n === tildes.length; });
                }

                tildes.forEach(function (t) { t.addEventListener('change', refrescar); });

                todos.forEach(function (maestro) {
                    maestro.addEventListener('change', function () {
                        tildes.forEach(function (t) { t.checked = maestro.checked; });
                        refrescar();
                    });
                });

                form.addEventListener('submit', function (e) {
                    var ids = elegidos().map(function (t) { return t.value; });

                    if (!ids.length) { e.preventDefault(); return; }

                    if (!confirm('Se le va a rehacer el detalle a ' + ids.length + ' partido(s) SIN vista previa: '
                            + 'se reemplaza alineación, goles, tarjetas, cambios y árbitros con lo que diga '
                            + 'Transfermarkt.\n\nCuesta ' + ids.length + ' llamada(s) a la API (más las fotos de los '
                            + 'jugadores nuevos) y puede tardar un rato. No cierres la pestaña.\n\n¿Seguimos?')) {
                        e.preventDefault();
                        return;
                    }

                    campo.value = ids.join(',');

                    // Después del submit, no durante: deshabilitar el botón en
                    // pleno envío puede quedar a mitad de camino en algún
                    // navegador. Es sólo para que no se apriete dos veces.
                    setTimeout(function () {
                        boton.disabled = true;
                        boton.textContent = 'Rehaciendo ' + ids.length + '...';
                    }, 0);
                });

                refrescar();
            })();
        </script>
    @endif

    <div class="row ctrl-pie">
        <div class="col-md-9">{{ $filas->links() }}</div>
        <div class="col-md-3 ctrl-resumen" style="text-align:right">
            <strong>{{ number_format($filas->total(), 0, ',', '.') }}</strong>
            {{ $filas->total() === 1 ? 'caso' : 'casos' }}
        </div>
    </div>
@endif
