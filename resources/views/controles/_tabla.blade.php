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
        // ── La selección: un tilde por PARTIDO ─────────────────────────────
        //
        // Uno por partido y no por fila: en los controles por jugador el mismo
        // partido aparece varias veces (dos goleadores, dos amonestados) y
        // hacerle dos veces lo mismo son dos llamadas por el mismo dato, o dos
        // incidencias donde alcanza una. La segunda fila del mismo partido
        // muestra «↑».
        //
        // Tildan TODAS las filas, tengan gameId o no: de los dos botones del
        // lote, uno lo necesita y el otro no. Lo que cada botón puede hacer con
        // lo tildado lo decide él:
        //
        //   Rehacer     - necesita gameId anotado. Sin eso tendría que salir a
        //                 buscarlo a Transfermarkt y puede terminar ofreciendo
        //                 candidatos para elegir, y eso se decide de a uno: el
        //                 lote se lo saltea y lo dice en el informe.
        //   Sin datos   - no necesita nada: escribe una incidencia en nuestra
        //                 base y no le pregunta nada a nadie.
        $conTilde   = [];
        $sinGameId  = 0;
        foreach ($filas as $filaTilde) {
            if (isset($conTilde[$filaTilde->id])) continue;
            $conTilde[$filaTilde->id] = !empty($filaTilde->tm_game_id);
            if (empty($filaTilde->tm_game_id)) $sinGameId++;
        }
        $tildePuesto = [];
        // El botón de la incidencia sale en los mismos controles que el de la
        // fila: marcar la excepción POR FALTA DE DATOS tiene sentido donde el
        // error puede venir del origen. Ver `Controles::definiciones()`.
        $loteSinDatos = !empty($def['sin_datos']);
        $sinDatos     = $sinDatos ?? ['boton' => 'Sin datos en TM', 'texto' => ''];
    @endphp

    @if(!empty($conTilde))
        <div class="ctrl-lote">
            {{-- El "tildar todos" va afuera de los dos forms: no tiene `name`,
                 no se envía, es sólo el interruptor de la columna. --}}
            <label class="ctrl-lote-todos">
                <input type="checkbox" id="ctrl-lote-todos">
                Tildar los {{ count($conTilde) }} de esta página
            </label>

            {{-- Dos acciones, dos forms, un solo tilde.
                 Los checkbox NO están adentro de ninguno de los dos: cada fila
                 ya tiene su propio form (el "Sin datos en TM" de a uno) y un
                 form adentro de otro form no existe en HTML — el navegador lo
                 desarma y se pierde uno. Los ids los copia el JS al enviar. --}}
            <form method="POST" action="{{ route('controles.rehacer') }}" class="ctrl-lote-form"
                  data-confirm="Se le va a rehacer el detalle a %n partido(s) SIN vista previa: se reemplaza alineación, goles, tarjetas, cambios y árbitros con lo que diga Transfermarkt.&#10;&#10;Cuesta hasta %n llamada(s) a la API (más las fotos de los jugadores nuevos) y puede tardar un rato. No cierres la pestaña.&#10;&#10;¿Seguimos?"
                  data-trabajando="Rehaciendo %n..."
                  data-saltea-sin-gameid="1">
                @csrf
                <input type="hidden" name="ids" class="ctrl-lote-ids" value="">
                <button type="submit" class="ctrl-lote-boton" disabled
                        data-texto="Rehacer %n seleccionado(s)"
                        title="Vuelve a bajar el detalle de los partidos tildados desde Transfermarkt y lo escribe. Cuesta 1 llamada por partido.">Rehacer seleccionados</button>
            </form>

            @if($loteSinDatos)
                <form method="POST" action="{{ route('controles.sinDatosLote') }}" class="ctrl-lote-form"
                      data-motivo="{{ $sinDatos['texto'] }}"
                      data-confirm="Se va a cargar esta incidencia en %n partido(s):&#10;&#10;{{ $sinDatos['texto'] }}&#10;&#10;Cada uno deja de aparecer en TODOS los controles. No gasta ninguna llamada a Transfermarkt. ¿Seguro?"
                      data-trabajando="Marcando %n...">
                    @csrf
                    <input type="hidden" name="ids" class="ctrl-lote-ids" value="">
                    <input type="hidden" name="check" value="{{ $clave }}">
                    <button type="submit" class="ctrl-lote-boton sindatos" disabled
                            data-texto="{{ $sinDatos['boton'] }} en %n"
                            title="{{ $sinDatos['texto'] }} Se carga como incidencia en cada partido tildado y esos partidos salen de los controles. No gasta llamadas.">{{ $sinDatos['boton'] }} en los seleccionados</button>
                </form>
            @endif

            <span class="ctrl-lote-nota">
                <b>Rehacer</b> baja y <b>escribe</b> el detalle de los tildados sin pasar por la vista previa:
                reemplaza alineación, goles, tarjetas, cambios y árbitros. <b>1 llamada por partido</b> (más las
                fotos de los jugadores nuevos), así que puede tardar un rato.
                @if($sinGameId)
                    {{ $sinGameId === 1 ? 'Hay 1 partido sin gameId anotado (marcado con *)' : 'Hay '.$sinGameId.' partidos sin gameId anotado (marcados con *)' }}:
                    a esos Rehacer los saltea, y van de a uno con el botón «Rehacer» de la fila, que lo busca en Transfermarkt.
                @endif
                @if($loteSinDatos)
                    <br><b>{{ $sinDatos['boton'] }}</b> no gasta llamadas: carga la incidencia en cada tildado y esos
                    partidos salen de todos los controles. Es para lo que no tiene arreglo posible, no para
                    esconder un error nuestro.
                @endif
            </span>
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
                            @if(!isset($tildePuesto[$fila->id]))
                                @php $tildePuesto[$fila->id] = true; @endphp
                                <input type="checkbox" class="ctrl-tilde" value="{{ $fila->id }}"
                                       @if(!$fila->tm_game_id) data-sin-gameid="1" @endif
                                       title="{{ $fila->tm_game_id
                                            ? 'Este partido entra en las dos acciones de arriba (gameId '.$fila->tm_game_id.')'
                                            : 'Sin gameId anotado: Rehacer lo saltea. Se puede marcar como incidencia, o rehacerlo de a uno con el botón «Rehacer» de la fila, que lo busca en Transfermarkt.' }}">
                                @if(!$fila->tm_game_id)
                                    <span class="ctrl-tilde-no" title="Sin gameId anotado: Rehacer lo saltea.">*</span>
                                @endif
                            @else
                                <span class="ctrl-tilde-repe" title="Este partido ya está tildado más arriba: se hace una sola vez.">↑</span>
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
                var forms = Array.prototype.slice.call(document.querySelectorAll('.ctrl-lote-form'));
                if (!forms.length) { return; }

                var tildes = Array.prototype.slice.call(document.querySelectorAll('.ctrl-tilde'));
                // Los dos "tildar todos": el de la barra y el del encabezado.
                var todos = [document.getElementById('ctrl-lote-todos'),
                             document.getElementById('ctrl-lote-todos-th')].filter(Boolean);

                function elegidos() {
                    return tildes.filter(function (t) { return t.checked; });
                }

                // Los textos vienen de la vista (data-texto, data-confirm,
                // data-trabajando) con %n donde va la cantidad: así los
                // castellanos largos quedan en el Blade y acá no hay ni una
                // frase escrita.
                function conN(texto, n) {
                    return String(texto || '').split('%n').join(n);
                }

                function refrescar() {
                    var n = elegidos().length;

                    todos.forEach(function (t) { t.checked = n > 0 && n === tildes.length; });

                    forms.forEach(function (form) {
                        var boton = form.querySelector('button[type="submit"]');
                        if (!boton) { return; }

                        if (!boton.dataset.original) { boton.dataset.original = boton.textContent.trim(); }

                        boton.disabled = n === 0;
                        boton.textContent = n === 0 ? boton.dataset.original : conN(boton.dataset.texto, n);
                    });
                }

                tildes.forEach(function (t) { t.addEventListener('change', refrescar); });

                todos.forEach(function (maestro) {
                    maestro.addEventListener('change', function () {
                        tildes.forEach(function (t) { t.checked = maestro.checked; });
                        refrescar();
                    });
                });

                forms.forEach(function (form) {
                    form.addEventListener('submit', function (e) {
                        var elegidas = elegidos();
                        var ids = elegidas.map(function (t) { return t.value; });

                        if (!ids.length) { e.preventDefault(); return; }

                        var aviso = conN(form.dataset.confirm, ids.length);

                        // Rehacer saltea los que no tienen gameId: se dice
                        // ANTES, no después en el informe. El botón de la
                        // incidencia no los saltea, así que no lo declara.
                        if (form.dataset.salteaSinGameid) {
                            var sin = elegidas.filter(function (t) { return t.dataset.sinGameid; }).length;
                            if (sin) {
                                aviso += '\n\nOjo: ' + sin + ' de los tildados no tiene(n) gameId anotado y los voy a '
                                    + 'saltear (van a salir en el informe).';
                            }
                        }

                        if (!confirm(aviso)) { e.preventDefault(); return; }

                        var campo = form.querySelector('.ctrl-lote-ids');
                        if (campo) { campo.value = ids.join(','); }

                        // Después del submit, no durante: deshabilitar el botón
                        // en pleno envío puede quedar a mitad de camino en algún
                        // navegador. Es sólo para que no se apriete dos veces.
                        setTimeout(function () {
                            forms.forEach(function (f) {
                                var b = f.querySelector('button[type="submit"]');
                                if (!b) { return; }
                                b.disabled = true;
                                if (f === form) { b.textContent = conN(f.dataset.trabajando, ids.length); }
                            });
                        }, 0);
                    });
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
