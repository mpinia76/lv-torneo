@extends('layouts.appPublic')

@section('pageTitle', __('Estadísticas'))

@section('content')

    @php
        /* Prefijo es: evita choques con variables globales de las vistas. */
        $esNum = function ($n, $dec = 2) {
            return number_format((float) $n, $dec, ',', '.');
        };
        $esFecha = function ($numero) {
            if ($numero === null || $numero === '') return '';
            return is_numeric($numero) ? __('Fecha :numero', ['numero' => $numero]) : trad_dato($numero);
        };
        $esHayTarjetas = false;
        foreach ($temporadas as $esT) {
            if ($esT->amarillas_pp !== null) { $esHayTarjetas = true; break; }
        }
        $esEvolucion = $competencia && count($evolucion) >= 2;
        $esEvolTarjetas = false;
        foreach ($evolucion as $esE) {
            if ($esE['tarjetas'] !== null) { $esEvolTarjetas = true; break; }
        }
    @endphp

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">{{ $zonaActual['nombre'] ?? '' }}{{ $competenciaActual ? ' · ' . $competenciaActual['nombre'] : '' }}</span>
            <h1>{{ __('Estadísticas') }}</h1>
        </div>
    </div>

    <form class="t-lista-filtros" method="GET" action="{{ route('torneos.estadisticasOtras') }}">
        @include('torneos._filtroZona')
        <noscript><button class="btn btn-outline-secondary btn-sm" type="submit">{{ __('Filtrar') }}</button></noscript>
    </form>

    @if($kpis['partidos'] == 0)
        <div class="t-panel">
            <div class="t-vacio"><i class="bi bi-bar-chart"></i>{{ __('No hay partidos jugados con esos filtros.') }}</div>
        </div>
    @else

        {{-- Números generales --}}
        <div class="t-kpis">
            <div class="t-kpi">
                <div class="t-kpi-num">{{ number_format($kpis['temporadas'], 0, ',', '.') }}</div>
                <div class="t-kpi-rot">{{ __('Temporadas') }}</div>
            </div>
            <div class="t-kpi">
                <div class="t-kpi-num">{{ number_format($kpis['partidos'], 0, ',', '.') }}</div>
                <div class="t-kpi-rot">{{ __('Partidos') }}</div>
            </div>
            <div class="t-kpi">
                <div class="t-kpi-num">{{ number_format($kpis['goles'], 0, ',', '.') }}</div>
                <div class="t-kpi-rot">{{ __('Goles') }}</div>
            </div>
            <div class="t-kpi t-kpi-acento">
                <div class="t-kpi-num">{{ $esNum($kpis['promedio']) }}</div>
                <div class="t-kpi-rot">{{ __('Goles por partido') }}</div>
            </div>
            @if($kpis['local'] !== null)
                <div class="t-kpi">
                    <div class="t-kpi-num">{{ $esNum($kpis['local'], 1) }}<small>%</small></div>
                    <div class="t-kpi-rot">{{ __('Gana el local') }}</div>
                </div>
            @endif
        </div>

        {{-- Evolución por temporada: solo con una competencia (mezclar ligas y copas no dice nada) --}}
        <h2 class="t-seccion">{{ __('Evolución por temporada') }}</h2>
        @if($esEvolucion)
            <div class="t-graf-grilla">
                <div class="t-panel t-panel-cuerpo">
                    <h3 class="t-graf-tit">{{ __('Goles por partido') }}</h3>
                    <div class="t-graf" id="grafGoles" role="img" aria-label="{{ __('Goles por partido') }}"></div>
                </div>
                <div class="t-panel t-panel-cuerpo">
                    <h3 class="t-graf-tit">{{ __('% de partidos que gana el local') }}</h3>
                    <div class="t-graf" id="grafLocal" role="img" aria-label="{{ __('% de partidos que gana el local') }}"></div>
                </div>
                @if($esEvolTarjetas)
                    <div class="t-panel t-panel-cuerpo">
                        <h3 class="t-graf-tit">{{ __('Tarjetas por partido') }}</h3>
                        <div class="t-graf" id="grafTarjetas" role="img" aria-label="{{ __('Tarjetas por partido') }}"></div>
                    </div>
                @endif
            </div>
            <p class="t-lista-ayuda mt-2">
                {{ __('Los datos de cada temporada están en la tabla del final.') }}
                @if($esEvolTarjetas) {{ __('Las tarjetas se muestran solo en las temporadas con al menos la mitad de los partidos con detalle cargado.') }} @endif
            </p>
        @else
            <p class="t-lista-ayuda">{{ __('Elegí una competencia para ver cómo cambió temporada a temporada.') }}</p>
        @endif

        {{-- Récords --}}
        <h2 class="t-seccion">{{ __('Récords') }}</h2>
        <div class="t-grilla-2">
            <div>
                <h3 class="t-graf-tit">{{ __('Partidos con más goles') }}</h3>
                <x-estadisticas-partidos :data="$record['masGoles']" :showTorneo="true"/>
            </div>
            <div>
                <h3 class="t-graf-tit">{{ __('Mayores goleadas') }}</h3>
                <x-estadisticas-partidos :data="$record['goleadas']" :showTorneo="true"/>
            </div>
        </div>

        <div class="t-grilla-2 mt-3">
            <div>
                <h3 class="t-graf-tit">{{ __('Temporadas con más goles por partido') }}</h3>
                @include('torneos._estadisticasTemporadas', ['esFilas' => $record['tempMas']])
            </div>
            <div>
                <h3 class="t-graf-tit">{{ __('Temporadas con menos goles por partido') }}</h3>
                @include('torneos._estadisticasTemporadas', ['esFilas' => $record['tempMenos']])
            </div>
        </div>
        <p class="t-lista-ayuda mt-2">{{ __('Solo temporadas con al menos :n partidos.', ['n' => \App\Http\Controllers\TorneoController::MIN_PARTIDOS_TEMPORADA]) }}</p>

        @if(count($record['fechas']))
            <h3 class="t-graf-tit mt-3">{{ __('Fechas con más goles por partido') }}</h3>
            <div class="t-panel">
                <div class="t-tabla-wrap">
                    <table class="t-tabla t-lista-tabla">
                        <thead>
                        <tr>
                            <th>{{ __('Torneo') }}</th>
                            <th>{{ __('Fecha') }}</th>
                            <th>{{ __('PJ') }}</th>
                            <th>{{ __('Goles') }}</th>
                            <th>{{ __('Prom.') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($record['fechas'] as $esF)
                            <tr>
                                <td>
                                    <a class="t-torneo" href="{{ route('torneos.ver', ['torneoId' => $esF->torneo_id]) }}">
                                        <x-escudo :src="$esF->escudoTorneo" :nombre="$esF->nombreTorneo" tam="sm"/>
                                        {{ $esF->nombreTorneo }} {{ $esF->year }}
                                    </a>
                                </td>
                                <td>{{ $esFecha($esF->numero) }}</td>
                                <td>{{ $esF->partidos }}</td>
                                <td>{{ $esF->goles }}</td>
                                <td class="t-pts">{{ $esNum($esF->promedio) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="t-lista-ayuda mt-2">{{ __('Solo fechas con al menos :n partidos.', ['n' => \App\Http\Controllers\TorneoController::MIN_PARTIDOS_FECHA]) }}</p>
        @endif

        {{-- Resumen por temporada --}}
        <h2 class="t-seccion">{{ __('Temporada por temporada') }}</h2>
        <div class="t-panel">
            <div class="t-tabla-wrap">
                <table class="t-tabla t-lista-tabla">
                    <thead>
                    <tr>
                        <th>{{ __('Temporada') }}</th>
                        <th title="{{ __('Partidos jugados') }}">{{ __('PJ') }}</th>
                        <th>{{ __('Goles') }}</th>
                        <th title="{{ __('Goles por partido') }}">{{ __('Prom.') }}</th>
                        <th title="{{ __('Gana el local · empate · gana el visitante (sin cancha neutral)') }}">{{ __('L · E · V') }}</th>
                        <th title="{{ __('Partidos sin goles') }}">0-0</th>
                        <th title="{{ __('Más goles en un partido') }}">{{ __('Máx.') }}</th>
                        @if($esHayTarjetas)
                            <th title="{{ __('Amarillas por partido (sobre partidos con detalle)') }}">{{ __('Am./PJ') }}</th>
                            <th title="{{ __('Rojas por partido (sobre partidos con detalle)') }}">{{ __('Ro./PJ') }}</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($temporadas as $esT)
                        @php
                            $esBase = $esT->no_neutrales;
                            $esL = $esBase ? $esT->gana_local * 100 / $esBase : 0;
                            $esE = $esBase ? $esT->empata * 100 / $esBase : 0;
                            $esV = $esBase ? $esT->gana_visita * 100 / $esBase : 0;
                        @endphp
                        <tr>
                            <td>
                                <a class="t-torneo" href="{{ route('torneos.ver', ['torneoId' => $esT->torneo_id]) }}">
                                    <x-escudo :src="$esT->escudo" :nombre="$esT->nombre" tam="sm"/>
                                    {{ $esT->nombre }} {{ $esT->year }}
                                </a>
                                @if($esT->parcial)<span class="t-chip ms-1" title="{{ __('Solo algunos partidos de la temporada') }}">{{ __('Parcial') }}</span>@endif
                            </td>
                            <td>{{ $esT->partidos }}</td>
                            <td>{{ $esT->goles }}</td>
                            <td class="t-pts">{{ $esNum($esT->promedio) }}</td>
                            <td>
                                @if($esBase)
                                    <span class="t-ge" title="{{ __('Local :l% · Empate :e% · Visitante :v%', ['l' => $esNum($esL, 1), 'e' => $esNum($esE, 1), 'v' => $esNum($esV, 1)]) }}">
                                        <i class="g" style="width: {{ round($esL, 1) }}%"></i>
                                        <i class="e" style="width: {{ round($esE, 1) }}%"></i>
                                        <i class="p" style="width: {{ round($esV, 1) }}%"></i>
                                    </span>
                                    <span class="t-num ms-1">{{ $esNum($esL, 0) }}%</span>
                                @else
                                    <span class="t-cero">–</span>
                                @endif
                            </td>
                            <td>{{ $esT->sin_goles }}</td>
                            <td>{{ $esT->max_goles }}</td>
                            @if($esHayTarjetas)
                                <td>{!! $esT->amarillas_pp !== null ? e($esNum($esT->amarillas_pp)) : '<span class="t-cero">–</span>' !!}</td>
                                <td>{!! $esT->rojas_pp !== null ? e($esNum($esT->rojas_pp)) : '<span class="t-cero">–</span>' !!}</td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="t-panel-pie">
                <div>{{ __('La barra L · E · V es sobre los partidos sin cancha neutral; el porcentaje, los que ganó el local.') }}</div>
            </div>
        </div>
    @endif

    <div class="d-flex mt-3">
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">{{ __('Volver') }}</a>
    </div>

    @if($kpis['partidos'] > 0 && $esEvolucion)
        <script src="{{ asset('js/echarts.min.js') }}"></script>
        <script>
            (function () {
                var datos = @json($evolucion);
                var etiquetas = datos.map(function (d) { return d.temporada; });
                var graficos = [];

                // Colores del tema del sitio (cambian con el modo oscuro).
                function tokens() {
                    var cs = getComputedStyle(document.documentElement);
                    function v(n) { return cs.getPropertyValue(n).trim(); }
                    return { acento: v('--t-accent'), linea: v('--t-line'), apagado: v('--t-muted'), tinta: v('--t-ink'), fondo: v('--t-surface') };
                }

                function numero(x, dec) {
                    return x === null || x === undefined ? '–' : Number(x).toLocaleString(@json(app()->getLocale() === 'es' ? 'es-AR' : 'en'), { minimumFractionDigits: dec, maximumFractionDigits: dec });
                }

                function dibujar(id, campo, sufijo, dec) {
                    var el = document.getElementById(id);
                    if (!el) return;
                    var t = tokens();
                    var g = echarts.getInstanceByDom(el) || echarts.init(el);
                    g.setOption({
                        animation: false,
                        grid: { left: 40, right: 12, top: 12, bottom: 28 },
                        tooltip: {
                            trigger: 'axis',
                            backgroundColor: t.fondo, borderColor: t.linea,
                            textStyle: { color: t.tinta, fontSize: 12 },
                            axisPointer: { type: 'line', lineStyle: { color: t.apagado } },
                            formatter: function (p) {
                                return p[0].axisValue + '<br><b>' + numero(p[0].value, dec) + sufijo + '</b>';
                            }
                        },
                        xAxis: {
                            type: 'category', data: etiquetas, boundaryGap: false,
                            axisLine: { lineStyle: { color: t.linea } }, axisTick: { show: false },
                            axisLabel: { color: t.apagado, fontSize: 10, hideOverlap: true }
                        },
                        yAxis: {
                            type: 'value', scale: true,
                            splitLine: { lineStyle: { color: t.linea, opacity: .6 } },
                            axisLabel: { color: t.apagado, fontSize: 10, formatter: function (v) { return numero(v, dec) + sufijo; } }
                        },
                        series: [{
                            type: 'line', data: datos.map(function (d) { return d[campo]; }),
                            connectNulls: false, showSymbol: datos.length <= 40, symbolSize: 8,
                            lineStyle: { width: 2, color: t.acento }, itemStyle: { color: t.acento }
                        }]
                    }, true);
                    graficos.push(g);
                }

                function todos() {
                    graficos = [];
                    dibujar('grafGoles', 'goles', '', 2);
                    dibujar('grafLocal', 'local', '%', 1);
                    dibujar('grafTarjetas', 'tarjetas', '', 2);
                }

                todos();
                window.addEventListener('resize', function () { graficos.forEach(function (g) { g.resize(); }); });
                new MutationObserver(todos).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
            })();
        </script>
    @endif

@endsection
