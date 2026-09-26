@extends('layouts.appPublic')

@section('pageTitle', __('Títulos'))

@section('content')

    @php
        /* Prefijo tt: $torneos, $grupo y $i están tomadas a nivel global. */
        /* argentinos es el parámetro viejo: ya no se arrastra. */
        $ttQuery = request()->except('page', 'argentinos');
        $ttLink  = function (array $extra = []) use ($ttQuery) {
            return route('torneos.titulos', array_merge($ttQuery, $extra));
        };

        $ttColumnas = [
            'titulos'         => ['rot' => __('Títulos'),         'tit' => __('Total de títulos')],
            'ligas'           => ['rot' => __('Ligas'),           'tit' => __('Ligas nacionales')],
            'copas'           => ['rot' => __('Copas'),           'tit' => __('Copas nacionales')],
            'internacionales' => ['rot' => __('Internacionales'), 'tit' => __('Títulos internacionales')],
        ];

        /* En una zona nacional no hay internacionales, y en una internacional no hay
           ligas ni copas nacionales: las columnas vacías no se muestran. */
        foreach (['ligas', 'copas', 'internacionales'] as $ttK) {
            if ($kpis['titulos'] > 0 && $kpis[$ttK] == 0) {
                unset($ttColumnas[$ttK]);
            }
        }
        $ttReparto = count($ttColumnas) > 2;

        $ttCols   = count($ttColumnas) + 2 + ($ttReparto ? 1 : 0); // #, equipo, reparto
        $ttBuscar = request()->get('buscarpor', session('nombre_filtro_equipo'));
        $ttCero   = function ($v) { return $v > 0 ? $v : '<span class="t-cero">0</span>'; };
    @endphp

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">{{ $zonaActual['nombre'] ?? __('Todas las zonas') }}{{ $competenciaActual ? ' · ' . $competenciaActual['nombre'] : '' }}</span>
            <h1>{{ __('Títulos') }}</h1>
        </div>

        <form class="t-lista-busqueda" method="GET" action="{{ route('torneos.titulos') }}">
            <input type="hidden" name="order" value="{{ $order }}">
            <input type="hidden" name="tipoOrder" value="{{ $tipoOrder }}">
            @if($zona !== '')<input type="hidden" name="zona" value="{{ $zona }}">@endif
            @if($competencia)<input type="hidden" name="competencia" value="{{ $competencia }}">@endif
            @if($paisEquipo)<input type="hidden" name="paisEquipo" value="{{ $paisEquipo }}">@endif
            <input type="search" name="buscarpor" class="form-control form-control-sm"
                   placeholder="{{ __('Buscar equipo') }}" value="{{ $ttBuscar }}">
            <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="t-kpis">
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['equipos'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">{{ __('Equipos campeones') }}</div>
        </div>
        <div class="t-kpi t-kpi-acento">
            <div class="t-kpi-num">{{ number_format($kpis['titulos'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">{{ __('Títulos') }}</div>
        </div>
        @if(isset($ttColumnas['ligas']))
            <div class="t-kpi">
                <div class="t-kpi-num">{{ number_format($kpis['ligas'], 0, ',', '.') }}</div>
                <div class="t-kpi-rot">{{ __('Ligas') }}</div>
            </div>
        @endif
        @if(isset($ttColumnas['copas']))
            <div class="t-kpi">
                <div class="t-kpi-num">{{ number_format($kpis['copas'], 0, ',', '.') }}</div>
                <div class="t-kpi-rot">{{ __('Copas') }}</div>
            </div>
        @endif
        @if(isset($ttColumnas['internacionales']))
            <div class="t-kpi">
                <div class="t-kpi-num">{{ number_format($kpis['internacionales'], 0, ',', '.') }}</div>
                <div class="t-kpi-rot">{{ __('Internacionales') }}</div>
            </div>
        @endif
    </div>

    <form class="t-lista-filtros" method="GET" action="{{ route('torneos.titulos') }}">
        <input type="hidden" name="order" value="{{ $order }}">
        <input type="hidden" name="tipoOrder" value="{{ $tipoOrder }}">

        @include('torneos._filtroZona')

        @if($filtraPais && count($paisesEquipos) > 1)
            <label class="t-lista-rot" for="ttPais">{{ __('Equipos de') }}</label>
            <select id="ttPais" name="paisEquipo" class="t-lista-select" onchange="this.form.submit()">
                <option value="">{{ __('Todos los países') }}</option>
                @foreach($paisesEquipos as $ttPais)
                    <option value="{{ $ttPais }}" @if($ttPais === $paisEquipo) selected @endif>{{ trad_dato($ttPais) }}</option>
                @endforeach
            </select>
        @endif

        @if($ttBuscar)
            <a class="t-chip t-chip-acento" href="{{ $ttLink(['buscarpor' => '']) }}">
                <i class="bi bi-x-lg"></i> “{{ $ttBuscar }}”
            </a>
        @endif
        @if($ttReparto)
            <span class="t-lista-ayuda ms-auto">
                @isset($ttColumnas['ligas'])<span class="t-referencia"><i class="t-ref-liga"></i> {{ __('Ligas') }}</span>@endisset
                @isset($ttColumnas['copas'])<span class="t-referencia"><i class="t-ref-copa"></i> {{ __('Copas') }}</span>@endisset
                @isset($ttColumnas['internacionales'])<span class="t-referencia"><i class="t-ref-inter"></i> {{ __('Internacionales') }}</span>@endisset
            </span>
        @endif

        <noscript><button class="btn btn-outline-secondary btn-sm" type="submit">{{ __('Filtrar') }}</button></noscript>
    </form>

    <div class="t-panel">
        <div class="t-tabla-wrap">
            <table class="t-tabla t-lista-tabla">
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Equipo') }}</th>
                    @foreach($ttColumnas as $ttKey => $ttCol)
                        <th title="{{ $ttCol['tit'] }}" class="{{ $order == $ttKey ? 't-orden-activo' : '' }}">
                            <a href="{{ $ttLink(['order' => $ttKey, 'tipoOrder' => ($order == $ttKey && $tipoOrder == 'DESC') ? 'ASC' : 'DESC']) }}">
                                {{ $ttCol['rot'] }}
                                @if($order == $ttKey)
                                    <i class="bi {{ $tipoOrder == 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                                @endif
                            </a>
                        </th>
                    @endforeach
                    @if($ttReparto)
                        <th title="{{ __('Cómo se reparten los títulos') }}">{{ __('Reparto') }}</th>
                    @endif
                </tr>
                </thead>

                <tbody>
                @forelse($posiciones as $equipo)
                    @php
                        $ttTotal = $equipo->ligas + $equipo->copas + $equipo->internacionales;
                    @endphp

                    <tr>
                        <td class="t-pos">{{ $i++ }}</td>
                        <td>
                            <x-celda-equipo :href="route('equipos.ver', ['equipoId' => $equipo->id])"
                                            :nombre="$equipo->nombre"
                                            :escudo="$equipo->escudo"
                                            :pais="$equipo->pais"/>
                        </td>

                        <td class="t-pts">
                            <span class="t-chip t-chip-acento"><i class="bi bi-trophy-fill"></i>{{ $equipo->titulos }}</span>
                        </td>
                        @isset($ttColumnas['ligas'])<td>{!! $ttCero($equipo->ligas) !!}</td>@endisset
                        @isset($ttColumnas['copas'])<td>{!! $ttCero($equipo->copas) !!}</td>@endisset
                        @isset($ttColumnas['internacionales'])<td>{!! $ttCero($equipo->internacionales) !!}</td>@endisset

                        @if($ttReparto)
                        <td>
                            @if($ttTotal > 0)
                                <span class="t-reparto"
                                      title="{{ __(':ligas ligas · :copas copas · :inter internacionales', ['ligas' => $equipo->ligas, 'copas' => $equipo->copas, 'inter' => $equipo->internacionales]) }}">
                                    <i class="l" style="width: {{ round($equipo->ligas * 100 / $ttTotal, 1) }}%"></i>
                                    <i class="c" style="width: {{ round($equipo->copas * 100 / $ttTotal, 1) }}%"></i>
                                    <i class="n" style="width: {{ round($equipo->internacionales * 100 / $ttTotal, 1) }}%"></i>
                                </span>
                            @endif
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $ttCols }}">
                            <div class="t-vacio"><i class="bi bi-trophy"></i>{{ __('No hay equipos con esos filtros.') }}</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="t-panel-pie">
            <div>{{ trans_choice(':n equipo|:n equipos', $posiciones->total(), ['n' => number_format($posiciones->total(), 0, ',', '.')]) }}</div>
            <div class="ms-auto t-paginacion">{{ $posiciones->appends($ttQuery)->links() }}</div>
        </div>
    </div>

    @if($palmares)
        <h2 class="t-seccion">{{ __('Campeones temporada por temporada') }}</h2>
        <div class="t-panel">
            <div class="t-tabla-wrap">
                <table class="t-tabla t-lista-tabla">
                    <thead>
                    <tr>
                        <th>{{ __('Temporada') }}</th>
                        <th>{{ __('Campeón') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($palmares as $ttP)
                        <tr>
                            <td>
                                <a class="t-torneo" href="{{ route('torneos.ver', ['torneoId' => $ttP->torneo_id]) }}">
                                    <x-escudo :src="$competenciaActual['escudo']" :nombre="$competenciaActual['nombre']" tam="sm"/>
                                    {{ $competenciaActual['nombre'] }} {{ $ttP->year }}
                                </a>
                            </td>
                            <td>
                                @forelse($ttP->campeones as $ttC)
                                    <x-celda-equipo :href="route('equipos.ver', ['equipoId' => $ttC->id])"
                                                    :nombre="$ttC->nombre" :escudo="$ttC->escudo" :pais="$ttC->pais"/>
                                @empty
                                    <span class="t-cero">{{ __('Sin campeón cargado') }}</span>
                                @endforelse
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="d-flex mt-3">
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">{{ __('Volver') }}</a>
    </div>

@endsection
