@extends('layouts.app')

@section('pageTitle', 'Controles de carga')

@section('content')
    {{-- Va acá adentro a propósito: el layout carga Bootstrap 3 después de
         app.css, así que una hoja propia en el <head> perdería contra él.

         El `?v=` es la fecha del archivo: sin eso el navegador se queda con la
         hoja vieja y los botones nuevos salen sin estilo (un botón blanco con
         letra blanca no se ve). Cada vez que se toca el CSS, cambia la URL. --}}
    @php
        $cssControles = public_path('css/controles.css');
        $cssVersion   = is_file($cssControles) ? filemtime($cssControles) : null;
    @endphp
    <link href="{{ asset('css/controles.css').($cssVersion ? '?v='.$cssVersion : '') }}" rel="stylesheet">

    <div class="container-fluid ctrl">

        <h1 class="display-6">Controles de carga</h1>
        <p class="ctrl-intro">
            Cada control busca una inconsistencia distinta entre el resultado del partido y lo que hay cargado.
            Se ejecuta solo el que estás mirando; los totales del costado se calculan aparte y se guardan 15 minutos.
        </p>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (\Session::has('error'))
            <div class="alert alert-danger">{!! \Session::get('error') !!}</div>
        @endif
        @if (\Session::has('success'))
            <div class="alert alert-success">{{ \Session::get('success') }}</div>
        @endif

        {{-- ---------------------------------------------------------------
             Qué hizo el último lote, partido por partido. Sirve a las dos
             acciones de la barra ("Rehacer seleccionados" y el de la
             incidencia): las dos dejan el mismo `lote_informe` en la sesión,
             con su propio título.

             Va acá arriba y no en una pantalla propia: el POST vuelve a esta
             misma página del mismo control —así no se pierde el lugar en la
             lista— y los que se resolvieron ya no están en la tabla de abajo.
             Sin este bloque, la única señal de que algo salió mal sería que el
             caso sigue apareciendo.

             El link va a "Datos complementarios" (`fechas.show`), que es
             gratis. El de la vista previa del importador se ofrece SÓLO en los
             que fallaron rehaciendo (`previa`), y avisando, porque vuelve a
             bajar el partido y eso cuesta otra llamada. En el lote de
             incidencias no aparece: ahí no falló Transfermarkt.
        ---------------------------------------------------------------- --}}
        @if (\Session::has('lote_informe'))
            @php $lote = \Session::get('lote_informe'); @endphp
            <div class="ctrl-informe">
                <div class="ctrl-informe-tit">Qué hizo «{{ $lote['titulo'] }}»</div>
                @foreach($lote['filas'] as $hecho)
                    <div class="ctrl-informe-fila {{ $hecho['ok'] ? '' : 'mal' }}">
                        <span class="ctrl-informe-marca">{{ $hecho['ok'] ? '✔' : '✘' }}</span>
                        <b>{{ $hecho['partido'] }}</b>
                        <span class="ctrl-sub">#{{ $hecho['id'] }}</span> —
                        {{ $hecho['texto'] }}
                        @if($hecho['fecha_id'])
                            · <a href="{{ route('fechas.show', $hecho['fecha_id']) }}" target="_blank" rel="noopener">ver los datos</a>
                        @endif
                        @if(!$hecho['ok'] && !empty($hecho['previa']))
                            · <a href="{{ route('import_detalles.ver', ['partido_id' => $hecho['id']]) }}"
                                 target="_blank" rel="noopener"
                                 title="Abre la vista previa del importador. Vuelve a bajar el partido: cuesta otra llamada.">vista previa ↗</a>
                        @endif
                        @foreach($hecho['avisos'] as $aviso)
                            <div class="ctrl-informe-aviso">• {{ $aviso }}</div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif

        {{-- ---------------------------------------------------------------
             Filtros. Aplican a todos los controles por igual y viajan en la
             URL, así que el total cacheado es el de ESE recorte.
        ---------------------------------------------------------------- --}}
        <div class="ctrl-filtros">
            <div class="ctrl-acciones">
                <form method="POST" action="{{ route('controles.recalcular') }}" style="display:inline">
                    @csrf
                    @foreach($filtros as $k => $v)
                        @if($v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
                    @endforeach
                    <input type="hidden" name="check" value="{{ $clave }}">
                    <button class="btn btn-default btn-sm" title="Vuelve a calcular los totales del menú">
                        Recalcular totales
                    </button>
                </form>
                <a href="{{ route('controles.torneos') }}" class="btn btn-info btn-sm">Control de torneos</a>
                <a href="{{ route('torneos.index') }}" class="btn btn-success btn-sm">Volver a torneos</a>
            </div>

            <form method="GET" action="{{ route('controles.index') }}" style="display:inline">
                <input type="hidden" name="check" value="{{ $clave }}">

                <div class="ctrl-campo">
                    <label>Año</label>
                    <select name="year" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        @foreach($anios as $anio)
                            <option value="{{ $anio }}" @if($filtros['year'] == $anio) selected @endif>{{ $anio }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="ctrl-campo">
                    <label>Torneo</label>
                    <select name="torneo" id="ctrl-filtro-torneo">
                        <option value="">Todos</option>
                        {{-- $torneosFiltro y no $torneos: el composer global de
                             ComposerServiceProvider pisa $torneos en todas las vistas. --}}
                        @foreach($torneosFiltro as $torneoId => $torneoNombre)
                            <option value="{{ $torneoId }}" @if($filtros['torneo'] == $torneoId) selected @endif>{{ $torneoNombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="ctrl-campo">
                    <label>Equipo o torneo</label>
                    <input type="text" name="q" value="{{ $filtros['q'] }}" placeholder="buscar...">
                </div>

                <div class="ctrl-campo">
                    <button class="btn btn-primary btn-sm">Filtrar</button>
                    @if($filtros['year'] || $filtros['torneo'] || $filtros['q'])
                        <a class="btn btn-default btn-sm" href="{{ route('controles.index', ['check' => $clave]) }}">Limpiar</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="row">

            {{-- --------------------------------------------------- menú --- --}}
            <div class="col-md-3">
                {{-- OJO con los nombres de las variables de este @foreach: al final
                     de la vista, @extends le pasa al layout TODO lo que quedó
                     definido acá (get_defined_vars()), y el footer hace
                     `@if(isset($grupo) && $grupo->penales)` esperando un modelo
                     Grupo. Una variable llamada $grupo rompe el footer entero. --}}
                <div class="ctrl-menu">
                    @foreach($grupos as $nombreGrupo => $chequeosGrupo)
                        <div class="ctrl-menu-grupo">{{ $nombreGrupo }}</div>
                        @foreach($chequeosGrupo as $c => $d)
                            <a href="{{ route('controles.index', array_filter(['check' => $c] + $filtros)) }}"
                               class="{{ $c === $clave ? 'activo' : '' }}"
                               title="{{ $d['ayuda'] }}">
                                @if($c === $clave)
                                    {{-- El activo ya tiene el total: es el de la tabla. --}}
                                    <span class="ctrl-total {{ $filas->total() ? 'hay' : 'limpio' }}">{{ $filas->total() }}</span>
                                @else
                                    <span class="ctrl-total cargando" data-check="{{ $c }}"
                                          @if(in_array($c, \App\Services\Controles::PESADOS)) data-pesado="1" @endif>·</span>
                                @endif
                                {{ $d['titulo'] }}
                            </a>
                        @endforeach
                    @endforeach
                </div>
            </div>

            {{-- ----------------------------------------- chequeo activo --- --}}
            <div class="col-md-9">

                <div class="ctrl-cabecera">
                    <h2>{{ $def['grupo'] }} · {{ $def['titulo'] }}</h2>
                    <p class="ctrl-ayuda">{{ $def['ayuda'] }}</p>
                </div>

                @if(!empty($resumenRoles))
                    <div class="ctrl-aviso">
                        Roles cargados en toda la base:
                        @foreach($resumenRoles as $rol => $total)
                            <span class="ctrl-chip neutro">{{ $rol }}: {{ number_format($total, 0, ',', '.') }}</span>
                        @endforeach
                    </div>
                @endif

                @if(!empty($def['roles']))
                    <div class="ctrl-filtro-rol">
                        Ver solo a los que les falta:
                        <a href="{{ route('controles.index', array_filter(['check' => $clave] + array_merge($filtros, ['rol' => null]))) }}"
                           class="{{ $filtros['rol'] ? '' : 'activo' }}">cualquiera de los tres</a>
                        @foreach($rolesTerna as $rol => $etiqueta)
                            <a href="{{ route('controles.index', array_filter(['check' => $clave] + array_merge($filtros, ['rol' => $rol]))) }}"
                               class="{{ $filtros['rol'] === $rol ? 'activo' : '' }}">{{ $etiqueta }}</a>
                        @endforeach
                    </div>
                @endif

                @if(!empty($def['aplicar']))
                    <div class="ctrl-aviso">
                        <form method="POST" action="{{ route('controles.penales.aplicar') }}"
                              onsubmit="return confirm('Se van a crear los penales convertidos de todos los goles de penal que entren en el filtro actual. ¿Seguimos?')">
                            @csrf
                            @foreach($filtros as $k => $v)
                                @if($v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
                            @endforeach
                            <button class="btn btn-warning btn-sm">Crear los penales faltantes</button>
                            <span style="margin-left:.5rem">
                                Crea la fila en <code>penals</code> con el arquero que estaba en cancha.
                                Los que dicen "no se pudo determinar" quedan afuera.
                            </span>
                        </form>
                    </div>
                @endif

                @if(!empty($def['unir_cambios']))
                    <div class="ctrl-aviso">
                        <form method="POST" action="{{ route('controles.cambios.unir') }}"
                              onsubmit="return confirm('Se van a juntar las parejas donde una fila tiene el descuento (90+4) y la otra es la misma jugada escrita a la vieja (90 o 94). Las separadas por un minuto de verdad no se tocan. ¿Seguimos?')">
                            @csrf
                            @foreach($filtros as $k => $v)
                                @if($v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
                            @endforeach
                            <button class="btn btn-warning btn-sm">Juntar las parejas del descuento</button>
                            <span style="margin-left:.5rem">
                                No gasta ninguna llamada a Transfermarkt: arregla los
                                <code>90+4</code> contra <code>94</code> y los <code>90</code> contra
                                <code>90+2</code>, que son la misma jugada escrita de dos formas.
                                Un <code>63</code> contra un <code>64</code> queda como está.
                            </span>
                        </form>
                    </div>
                @endif

                @if(!empty($def['marcadores_staging']))
                    <div class="ctrl-aviso">
                        Antes de cargar nada a mano:
                        <a href="{{ route('import_detalles.resultados') }}" target="_blank" rel="noopener"
                           class="btn btn-warning btn-sm">Resultados de lo ya cargado</a>
                        <span style="margin-left:.5rem">
                            Le pone el marcador a los partidos que bajaste antes de que el detalle lo escribiera,
                            leyendo el fixture que ya está guardado en <code>import_partidos</code>.
                            <b>No gasta ninguna llamada a Transfermarkt</b> y nunca pisa un resultado cargado.
                            Los que quedan acá después de esa pasada son los que no tienen el dato en ningún lado.
                        </span>
                    </div>
                @endif

                @include('controles._tabla')

            </div>
        </div>
    </div>
@endsection

@section('bottom')
    {{-- jQuery y select2 vienen del layout. El torneo con buscador: la lista
         con "Todos" los años es larga y a ojo no se encuentra nada. --}}
    <script>
        $(function () {
            if (!$.fn.select2) { return; }
            $('#ctrl-filtro-torneo').select2({ width: '260px' });
        });
    </script>
    <script>
        // Totales del menú.
        //
        // Los livianos se piden de a POR_VEZ en paralelo (antes iban de a uno
        // en fila y el costado tardaba minuto y medio en completarse).
        //
        // Los pesados (Controles::PESADOS, de 5 a 45 s cada uno) NO se calculan
        // solos: se pide sólo lo que haya en cache y, si no hay nada, queda un
        // "calcular" para pedirlo a mano cuando haga falta.
        (function () {
            var POR_VEZ = 3;
            var filtros = @json(array_filter($filtros));
            var todos = Array.prototype.slice.call(document.querySelectorAll('.ctrl-total[data-check]'));
            var livianos = todos.filter(function (el) { return !el.hasAttribute('data-pesado'); });
            var pesados  = todos.filter(function (el) { return el.hasAttribute('data-pesado'); });

            function url(check, soloCache) {
                var qs = ['check=' + encodeURIComponent(check)];
                if (soloCache) { qs.push('cache=1'); }
                for (var k in filtros) {
                    if (filtros.hasOwnProperty(k)) {
                        qs.push(encodeURIComponent(k) + '=' + encodeURIComponent(filtros[k]));
                    }
                }
                return '{{ route('controles.conteo') }}?' + qs.join('&');
            }

            function pedir(el, soloCache) {
                return fetch(url(el.getAttribute('data-check'), soloCache), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        if (d.total === null || d.total === undefined) {
                            el.textContent = 'calcular';
                            el.className = 'ctrl-total calcular';
                            el.title = 'Este total es lento (varios segundos): se calcula sólo si lo pedís.';
                            return;
                        }
                        el.textContent = d.total;
                        el.className = 'ctrl-total ' + (d.total > 0 ? 'hay' : 'limpio');
                        el.removeAttribute('title');
                    })
                    .catch(function () { el.textContent = '?'; });
            }

            // Livianos: una cola atendida por POR_VEZ "trabajadores".
            function trabajador() {
                var el = livianos.shift();
                if (!el) { return Promise.resolve(); }
                return pedir(el, false).then(trabajador);
            }
            var hilos = [];
            for (var i = 0; i < POR_VEZ; i++) { hilos.push(trabajador()); }

            // Pesados: sólo lo cacheado, después de los livianos.
            Promise.all(hilos).then(function () {
                pesados.forEach(function (el) { pedir(el, true); });
            });

            // "calcular": el span está adentro del link del menú, así que el
            // clic se frena para no navegar.
            document.addEventListener('click', function (ev) {
                var el = ev.target.closest ? ev.target.closest('.ctrl-total.calcular') : null;
                if (!el) { return; }
                ev.preventDefault();
                ev.stopPropagation();
                el.textContent = '…';
                el.className = 'ctrl-total cargando';
                pedir(el, false);
            });
        })();
    </script>
@endsection
