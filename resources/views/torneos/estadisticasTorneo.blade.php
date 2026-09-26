@extends('layouts.appPublic')

@section('pageTitle', __('Estadísticas') . ' · ' . $torneo->nombre . ' ' . $torneo->year)

@section('content')

    @php
        /* Prefijo et: evita choques con variables globales de las vistas. */
        $etNum = function ($n, $dec = 2) {
            return number_format((float) $n, $dec, ',', '.');
        };
        $etFecha = function ($numero) {
            if ($numero === null || $numero === '') return '';
            return is_numeric($numero) ? __('Fecha :numero', ['numero' => $numero]) : trad_dato($numero);
        };
        $etK   = $est['kpis'];
        $etLev = $est['lev'];
        $etNN  = $etLev['l'] + $etLev['e'] + $etLev['v'];
        $etPct = function ($n, $base) { return $base ? $n * 100 / $base : 0; };
        $etHayTarjetas = false;
        foreach ($est['equipos'] as $etF) {
            if ($etF->amarillas !== null) { $etHayTarjetas = true; break; }
        }
        $etRachas = [
            'ganados'  => __('Victorias seguidas'),
            'invicto'  => __('Partidos sin perder'),
            'sinGanar' => __('Partidos sin ganar'),
        ];
        $etTipos = [
            'Jugada' => __('Jugada'), 'Cabeza' => __('Cabeza'), 'Penal' => __('Penal'),
            'Tiro Libre' => __('Tiro Libre'), 'Olímpico' => __('Olímpico'), 'En Contra' => __('En contra'),
        ];
    @endphp

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">
                <x-escudo :src="$torneo->escudo" :nombre="$torneo->nombre" tam="sm"/>
                {{ $torneo->nombre }} {{ $torneo->year }}
            </span>
            <h1>{{ __('Estadísticas') }}</h1>
        </div>
    </div>

    @if($etK['partidos'] == 0)
        <div class="t-panel">
            <div class="t-vacio"><i class="bi bi-bar-chart"></i>{{ __('Todavía no hay partidos jugados en este torneo.') }}</div>
        </div>
    @else

        {{-- Números generales --}}
        <div class="t-kpis">
            <div class="t-kpi">
                <div class="t-kpi-num">{{ $etK['partidos'] }}</div>
                <div class="t-kpi-rot">{{ __('Partidos') }}</div>
            </div>
            <div class="t-kpi">
                <div class="t-kpi-num">{{ $etK['goles'] }}</div>
                <div class="t-kpi-rot">{{ __('Goles') }}</div>
            </div>
            <div class="t-kpi t-kpi-acento">
                <div class="t-kpi-num">{{ $etNum($etK['promedio']) }}</div>
                <div class="t-kpi-rot">{{ __('Goles por partido') }}</div>
            </div>
            @if($etK['local'] !== null)
                <div class="t-kpi">
                    <div class="t-kpi-num">{{ $etNum($etK['local'], 1) }}<small>%</small></div>
                    <div class="t-kpi-rot">{{ __('Gana el local') }}</div>
                </div>
            @endif
            <div class="t-kpi">
                <div class="t-kpi-num">{{ $etK['sin_goles'] }}</div>
                <div class="t-kpi-rot">{{ __('Partidos sin goles') }}</div>
            </div>
            @if($etK['tarjetas_pp'] !== null)
                <div class="t-kpi">
                    <div class="t-kpi-num">{{ $etNum($etK['tarjetas_pp']) }}</div>
                    <div class="t-kpi-rot">{{ __('Tarjetas por partido') }}</div>
                </div>
            @endif
        </div>
        @if($etK['con_detalle'] < $etK['partidos'])
            <div class="t-kpis-pie">
                <span>{{ __('Con detalle cargado (goles, tarjetas, árbitros): :n de :total partidos.', ['n' => $etK['con_detalle'], 'total' => $etK['partidos']]) }}</span>
            </div>
        @endif

        {{-- Resultados --}}
        <h2 class="t-seccion">{{ __('Resultados') }}</h2>
        <div class="t-grilla-2">
            <div class="t-panel t-panel-cuerpo">
                <h3 class="t-graf-tit">{{ __('Marcadores más repetidos') }}</h3>
                <div class="t-barras">
                    @foreach($est['marcadores'] as $etM)
                        <div class="t-barras-fila">
                            <span class="t-barras-rot t-mono">{{ $etM['marcador'] }}</span>
                            <span class="t-barras-pista"><i style="width: {{ round($etM['pct'] * 100 / max(1, $est['marcadores'][0]['pct']), 1) }}%"></i></span>
                            <span class="t-barras-val">{{ $etM['n'] }} <small>· {{ $etNum($etM['pct'], 1) }}%</small></span>
                        </div>
                    @endforeach
                </div>
                <p class="t-lista-ayuda mt-2 mb-0">{{ __('Sin importar quién fue local: 1-0 y 0-1 cuentan juntos.') }}</p>
            </div>

            <div class="t-panel t-panel-cuerpo">
                @if($etNN)
                    <h3 class="t-graf-tit">{{ __('Local, empate y visitante') }}</h3>
                    <div class="t-barras">
                        @foreach(['l' => __('Gana el local'), 'e' => __('Empate'), 'v' => __('Gana el visitante')] as $etC => $etRot)
                            <div class="t-barras-fila">
                                <span class="t-barras-rot">{{ $etRot }}</span>
                                <span class="t-barras-pista t-barras-{{ $etC }}"><i style="width: {{ round($etPct($etLev[$etC], $etNN), 1) }}%"></i></span>
                                <span class="t-barras-val">{{ $etLev[$etC] }} <small>· {{ $etNum($etPct($etLev[$etC], $etNN), 1) }}%</small></span>
                            </div>
                        @endforeach
                    </div>
                    <p class="t-lista-ayuda mt-2">{{ __('Sin contar los partidos en cancha neutral. Los definidos por penales cuentan como empate.') }}</p>
                @endif

                @if($est['primero'])
                    @php $etP = $est['primero']; @endphp
                    <h3 class="t-graf-tit mt-3">{{ __('El que marcó primero…') }}</h3>
                    <div class="t-barras">
                        @foreach(['g' => __('ganó'), 'e' => __('empató'), 'p' => __('perdió')] as $etC => $etRot)
                            <div class="t-barras-fila">
                                <span class="t-barras-rot">{{ $etRot }}</span>
                                <span class="t-barras-pista t-barras-{{ $etC === 'g' ? 'l' : ($etC === 'e' ? 'e' : 'v') }}"><i style="width: {{ round($etPct($etP[$etC], $etP['n']), 1) }}%"></i></span>
                                <span class="t-barras-val">{{ $etP[$etC] }} <small>· {{ $etNum($etPct($etP[$etC], $etP['n']), 1) }}%</small></span>
                            </div>
                        @endforeach
                    </div>
                    <p class="t-lista-ayuda mt-2 mb-0">{{ __('Sobre :n partidos con todos los goles cargados con minuto.', ['n' => $etP['n']]) }}</p>
                @endif
            </div>
        </div>

        {{-- Goles --}}
        <h2 class="t-seccion">{{ __('Goles') }}</h2>
        @if(count($est['porFecha']) >= 3)
            <div class="t-panel t-panel-cuerpo">
                <h3 class="t-graf-tit">{{ __('Goles por partido en cada fecha') }}</h3>
                <div class="t-graf" id="grafFechas" role="img" aria-label="{{ __('Goles por partido en cada fecha') }}"></div>
            </div>
        @endif

        @if($est['golesMinuto'] || $est['golesTipo'])
            <div class="t-grilla-2 mt-3">
                @if($est['golesMinuto'])
                    <div class="t-panel t-panel-cuerpo">
                        <h3 class="t-graf-tit">{{ __('Goles por minuto') }}</h3>
                        <div class="t-graf" id="grafMinutos" role="img" aria-label="{{ __('Goles por minuto') }}"></div>
                        <p class="t-lista-ayuda mt-2 mb-0">
                            {{ __('Sobre el :pct% de los goles, los que tienen minuto cargado. El descuento cuenta en su tiempo (45+2 es primer tiempo).', ['pct' => $etNum($est['golesMinuto']['cobertura'], 0)]) }}
                        </p>
                    </div>
                @endif
                @if($est['golesTipo'])
                    <div class="t-panel t-panel-cuerpo">
                        <h3 class="t-graf-tit">{{ __('Cómo fueron los goles') }}</h3>
                        <div class="t-barras">
                            @foreach($est['golesTipo']['filas'] as $etT)
                                <div class="t-barras-fila">
                                    <span class="t-barras-rot">{{ $etTipos[$etT['tipo']] ?? $etT['tipo'] }}</span>
                                    <span class="t-barras-pista"><i style="width: {{ round($etT['pct'] * 100 / max(1, $est['golesTipo']['filas'][0]['pct']), 1) }}%"></i></span>
                                    <span class="t-barras-val">{{ $etT['n'] }} <small>· {{ $etNum($etT['pct'], 1) }}%</small></span>
                                </div>
                            @endforeach
                        </div>
                        <p class="t-lista-ayuda mt-2 mb-0">{{ __('Sobre el :pct% de los goles, los que tienen el tipo cargado.', ['pct' => $etNum($est['golesTipo']['cobertura'], 0)]) }}</p>
                    </div>
                @endif
            </div>
        @endif

        {{-- Equipos --}}
        <h2 class="t-seccion">{{ __('Equipos') }}</h2>
        <div class="t-panel">
            <div class="t-tabla-wrap">
                <table class="t-tabla t-lista-tabla" id="etTablaEquipos">
                    <thead>
                    <tr>
                        <th data-orden="texto">{{ __('Equipo') }}</th>
                        <th data-orden="num" title="{{ __('Partidos jugados') }}">{{ __('PJ') }}</th>
                        <th data-orden="num">{{ __('G') }}</th>
                        <th data-orden="num">{{ __('E') }}</th>
                        <th data-orden="num">{{ __('P') }}</th>
                        <th data-orden="num" title="{{ __('Goles a favor') }}">{{ __('GF') }}</th>
                        <th data-orden="num" title="{{ __('Goles en contra') }}">{{ __('GC') }}</th>
                        <th data-orden="num" title="{{ __('Diferencia de gol') }}">{{ __('Dif.') }}</th>
                        <th data-orden="num" title="{{ __('Goles a favor por partido') }}">{{ __('GF/PJ') }}</th>
                        <th data-orden="num" title="{{ __('Goles en contra por partido') }}">{{ __('GC/PJ') }}</th>
                        <th data-orden="num" title="{{ __('Partidos sin recibir goles') }}">{{ __('Vallas inv.') }}</th>
                        <th data-orden="num" title="{{ __('Efectividad de local (puntos sobre los que jugó)') }}">{{ __('Local') }}</th>
                        <th data-orden="num" title="{{ __('Efectividad de visitante (puntos sobre los que jugó)') }}">{{ __('Visitante') }}</th>
                        @if($etHayTarjetas)
                            <th data-orden="num" title="{{ __('Amarillas (partidos con detalle)') }}"><span class="t-tarjeta-a"></span></th>
                            <th data-orden="num" title="{{ __('Rojas, incluidas las dobles amarillas (partidos con detalle)') }}"><span class="t-tarjeta-r"></span></th>
                        @endif
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($est['equipos'] as $etF)
                        <tr>
                            <td data-valor="{{ $etF->nombre }}">
                                <x-celda-equipo :href="route('equipos.ver', ['equipoId' => $etF->equipo_id])"
                                                :nombre="$etF->nombre" :escudo="$etF->escudo" :pais="$etF->pais"/>
                            </td>
                            <td>{{ $etF->pj }}</td>
                            <td>{{ $etF->g }}</td>
                            <td>{{ $etF->e }}</td>
                            <td>{{ $etF->p }}</td>
                            <td>{{ $etF->gf }}</td>
                            <td>{{ $etF->gc }}</td>
                            <td data-valor="{{ $etF->dif }}" class="{{ $etF->dif > 0 ? 't-dif-pos' : ($etF->dif < 0 ? 't-dif-neg' : 't-cero') }}">{{ $etF->dif > 0 ? '+' : '' }}{{ $etF->dif }}</td>
                            <td data-valor="{{ $etF->gf / max(1, $etF->pj) }}">{{ $etNum($etF->gf / max(1, $etF->pj)) }}</td>
                            <td data-valor="{{ $etF->gc / max(1, $etF->pj) }}">{{ $etNum($etF->gc / max(1, $etF->pj)) }}</td>
                            <td>{{ $etF->vallas }}</td>
                            <td data-valor="{{ $etF->efect_local ?? -1 }}">{!! $etF->efect_local !== null ? e($etNum($etF->efect_local, 0)) . '%' : '<span class="t-cero">–</span>' !!}</td>
                            <td data-valor="{{ $etF->efect_visita ?? -1 }}">{!! $etF->efect_visita !== null ? e($etNum($etF->efect_visita, 0)) . '%' : '<span class="t-cero">–</span>' !!}</td>
                            @if($etHayTarjetas)
                                <td data-valor="{{ $etF->amarillas ?? -1 }}">{!! $etF->amarillas !== null ? $etF->amarillas : '<span class="t-cero">–</span>' !!}</td>
                                <td data-valor="{{ $etF->rojas ?? -1 }}">{!! $etF->rojas !== null ? $etF->rojas : '<span class="t-cero">–</span>' !!}</td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="t-panel-pie">
                <div>{{ __('Tocá un encabezado para ordenar. Ordenada por puntos, diferencia y goles a favor; no es la tabla de posiciones oficial (no tiene en cuenta zonas, descuentos ni desempates del reglamento).') }}</div>
            </div>
        </div>

        {{-- Récords --}}
        <h2 class="t-seccion">{{ __('Récords') }}</h2>
        <div class="t-grilla-2">
            <div>
                <h3 class="t-graf-tit">{{ __('Partidos con más goles') }}</h3>
                <x-estadisticas-partidos :data="$est['masGoles']"/>
            </div>
            <div>
                <h3 class="t-graf-tit">{{ __('Mayores goleadas') }}</h3>
                @if(count($est['goleadas']))
                    <x-estadisticas-partidos :data="$est['goleadas']"/>
                @else
                    <p class="t-lista-ayuda">{{ __('No hubo partidos ganados por dos goles o más.') }}</p>
                @endif
            </div>
        </div>

        @if(array_filter($est['rachas']))
            <h3 class="t-graf-tit mt-3">{{ __('Rachas') }}</h3>
            <div class="t-panel">
                <div class="t-tabla-wrap">
                    <table class="t-tabla t-lista-tabla">
                        <thead>
                        <tr>
                            <th>{{ __('Racha') }}</th>
                            <th>{{ __('Equipo') }}</th>
                            <th>{{ __('Partidos') }}</th>
                            <th>{{ __('Desde') }}</th>
                            <th>{{ __('Hasta') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($etRachas as $etClave => $etRot)
                            @foreach($est['rachas'][$etClave] as $etI => $etR)
                                <tr>
                                    <td>{{ $etI === 0 ? $etRot : '' }}</td>
                                    <td>
                                        @if($etR['equipo'])
                                            <x-celda-equipo :href="route('equipos.ver', ['equipoId' => $etR['equipo']->equipo_id])"
                                                            :nombre="$etR['equipo']->nombre" :escudo="$etR['equipo']->escudo" :pais="$etR['equipo']->pais"/>
                                        @endif
                                    </td>
                                    <td class="t-pts">{{ $etR['n'] }}</td>
                                    <td>{{ $etFecha($etR['desde']) }}</td>
                                    <td>{{ $etFecha($etR['hasta']) }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Árbitros --}}
        @if(count($est['arbitros']))
            <h2 class="t-seccion">{{ __('Árbitros') }}</h2>
            <div class="t-panel">
                <div class="t-tabla-wrap">
                    <table class="t-tabla t-lista-tabla">
                        <thead>
                        <tr>
                            <th>{{ __('Árbitro') }}</th>
                            <th title="{{ __('Partidos dirigidos') }}">{{ __('PJ') }}</th>
                            <th title="{{ __('Amarillas por partido') }}"><span class="t-tarjeta-a"></span>/{{ __('PJ') }}</th>
                            <th title="{{ __('Rojas por partido, incluidas las dobles amarillas') }}"><span class="t-tarjeta-r"></span>/{{ __('PJ') }}</th>
                            <th title="{{ __('Partidos que ganó el local (sin cancha neutral)') }}">{{ __('Gana el local') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($est['arbitros'] as $etA)
                            <tr>
                                <td>
                                    <x-celda-persona :href="route('arbitros.ver', ['arbitroId' => $etA->arbitro_id])"
                                                     :nombre="$etA->nombre" :foto="$etA->foto"/>
                                </td>
                                <td>{{ $etA->pj }}</td>
                                <td>{!! $etA->am_pp !== null ? e($etNum($etA->am_pp)) : '<span class="t-cero">–</span>' !!}</td>
                                <td>{!! $etA->ro_pp !== null ? e($etNum($etA->ro_pp)) : '<span class="t-cero">–</span>' !!}</td>
                                <td>{!! $etA->local_pct !== null ? e($etNum($etA->local_pct, 0)) . '%' : '<span class="t-cero">–</span>' !!}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif

    <div class="d-flex mt-3">
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">{{ __('Volver') }}</a>
    </div>

    @if($etK['partidos'] > 0)
        {{-- Ordenar la tabla de equipos tocando el encabezado. --}}
        <script>
            (function () {
                var tabla = document.getElementById('etTablaEquipos');
                if (!tabla) return;
                var ths = tabla.querySelectorAll('thead th');
                ths.forEach(function (th, col) {
                    var tipo = th.getAttribute('data-orden');
                    if (!tipo) return;
                    th.style.cursor = 'pointer';
                    th.addEventListener('click', function () {
                        var desc = th.getAttribute('data-dir') !== 'desc';
                        ths.forEach(function (o) { o.removeAttribute('data-dir'); o.classList.remove('t-orden-activo'); });
                        th.setAttribute('data-dir', desc ? 'desc' : 'asc');
                        th.classList.add('t-orden-activo');
                        var cuerpo = tabla.tBodies[0];
                        var filas = Array.prototype.slice.call(cuerpo.rows);
                        filas.sort(function (a, b) {
                            var ca = a.cells[col], cb = b.cells[col];
                            var va = ca.getAttribute('data-valor') !== null ? ca.getAttribute('data-valor') : ca.textContent.trim();
                            var vb = cb.getAttribute('data-valor') !== null ? cb.getAttribute('data-valor') : cb.textContent.trim();
                            var r = tipo === 'num' ? (parseFloat(va) || 0) - (parseFloat(vb) || 0) : va.localeCompare(vb);
                            return desc ? -r : r;
                        });
                        filas.forEach(function (f) { cuerpo.appendChild(f); });
                    });
                });
            })();
        </script>
    @endif

    @if($etK['partidos'] > 0 && (count($est['porFecha']) >= 3 || $est['golesMinuto']))
        <script src="{{ asset('js/echarts.min.js') }}"></script>
        <script>
            (function () {
                var porFecha = @json($est['porFecha']);
                var minutos  = @json($est['golesMinuto'] ? $est['golesMinuto']['franjas'] : null);
                var rotFecha = @json(__('Fecha :numero', ['numero' => '#']));
                var graficos = [];

                function tokens() {
                    var cs = getComputedStyle(document.documentElement);
                    function v(n) { return cs.getPropertyValue(n).trim(); }
                    return { acento: v('--t-accent'), linea: v('--t-line'), apagado: v('--t-muted'), tinta: v('--t-ink'), fondo: v('--t-surface') };
                }
                function numero(x, dec) {
                    return Number(x).toLocaleString(@json(app()->getLocale() === 'es' ? 'es-AR' : 'en'), { minimumFractionDigits: dec, maximumFractionDigits: dec });
                }
                function etiquetaFecha(n) {
                    return /^\d+$/.test(String(n)) ? rotFecha.replace('#', n) : String(n);
                }

                function barras(id, etiquetas, valores, dec, detalle) {
                    var el = document.getElementById(id);
                    if (!el) return;
                    var t = tokens();
                    var g = echarts.getInstanceByDom(el) || echarts.init(el);
                    g.setOption({
                        animation: false,
                        grid: { left: 36, right: 8, top: 12, bottom: 28 },
                        tooltip: {
                            trigger: 'axis', axisPointer: { type: 'shadow' },
                            backgroundColor: t.fondo, borderColor: t.linea, textStyle: { color: t.tinta, fontSize: 12 },
                            formatter: function (p) { return detalle(p[0].dataIndex); }
                        },
                        xAxis: {
                            type: 'category', data: etiquetas,
                            axisLine: { lineStyle: { color: t.linea } }, axisTick: { show: false },
                            axisLabel: { color: t.apagado, fontSize: 10, hideOverlap: true }
                        },
                        yAxis: {
                            type: 'value',
                            splitLine: { lineStyle: { color: t.linea, opacity: .6 } },
                            axisLabel: { color: t.apagado, fontSize: 10, formatter: function (v) { return numero(v, dec); } }
                        },
                        series: [{ type: 'bar', data: valores, barMaxWidth: 28, itemStyle: { color: t.acento, borderRadius: [4, 4, 0, 0] } }]
                    }, true);
                    graficos.push(g);
                }

                function todos() {
                    graficos = [];
                    if (porFecha.length >= 3) {
                        barras('grafFechas',
                            porFecha.map(function (f) { return /^\d+$/.test(String(f.numero)) ? String(f.numero) : String(f.numero).slice(0, 10); }),
                            porFecha.map(function (f) { return f.promedio; }), 1,
                            function (i) {
                                var f = porFecha[i];
                                return etiquetaFecha(f.numero) + '<br><b>' + numero(f.promedio, 2) + '</b> ' + @json(__('goles por partido'))
                                    + '<br>' + f.goles + ' ' + @json(__('goles en')) + ' ' + f.partidos + ' ' + @json(__('partidos'));
                            });
                    }
                    if (minutos) {
                        var rot = Object.keys(minutos);
                        barras('grafMinutos', rot, rot.map(function (k) { return minutos[k]; }), 0,
                            function (i) { return rot[i] + "'<br><b>" + minutos[rot[i]] + '</b> ' + @json(__('goles')); });
                    }
                }

                todos();
                window.addEventListener('resize', function () { graficos.forEach(function (g) { g.resize(); }); });
                new MutationObserver(todos).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
            })();
        </script>
    @endif

@endsection
