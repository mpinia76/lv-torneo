@extends('layouts.appPublic')

@section('pageTitle', __('Tabla histórica'))

@section('content')

    @php
        /* Prefijo th: $torneos, $grupo y $i están tomadas a nivel global. */
        $thQuery = request()->except('page');
        $thLink  = function (array $extra = []) use ($thQuery) {
            return route('torneos.posiciones', array_merge($thQuery, $extra));
        };

        $thColumnas = [
            'puntaje'    => ['rot' => __('Pts'),  'tit' => __('Puntos')],
            'jugados'    => ['rot' => __('J'),    'tit' => __('Partidos jugados')],
            'ganados'    => ['rot' => __('G'),    'tit' => __('Ganados')],
            'empatados'  => ['rot' => __('E'),    'tit' => __('Empatados')],
            'perdidos'   => ['rot' => __('P'),    'tit' => __('Perdidos')],
        ];

        $thColumnas2 = [
            'golesl'     => ['rot' => __('GF'),   'tit' => __('Goles a favor')],
            'golesv'     => ['rot' => __('GC'),   'tit' => __('Goles en contra')],
            'diferencia' => ['rot' => __('Dif.'), 'tit' => __('Diferencia de gol')],
            'promedio'   => ['rot' => '%',    'tit' => __('Efectividad sobre puntos en juego')],
        ];

        $thCols   = count($thColumnas) + count($thColumnas2) + 4; // #, equipo, balance, títulos
        $thBuscar = request()->get('buscarpor', session('nombre_filtro_equipo'));

        $thTipos = ['' => __('Todos'), 'liga' => __('Ligas'), 'copa' => __('Copas')];
        $thAmbitos = ['' => __('Todos'), 'nacional' => __('Nacionales'), 'internacional' => __('Internacionales')];
    @endphp

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">{{ __('Equipos') }}</span>
            <h1>{{ __('Tabla histórica') }}</h1>
        </div>

        <form class="t-lista-busqueda" method="GET" action="{{ route('torneos.posiciones') }}">
            <input type="hidden" name="tipo" value="{{ $tipo }}">
            <input type="hidden" name="ambito" value="{{ $ambito }}">
            <input type="hidden" name="order" value="{{ $order }}">
            <input type="hidden" name="tipoOrder" value="{{ $tipoOrder }}">
            @if($argentinos)<input type="hidden" name="argentinos" value="1">@endif
            <input type="search" name="buscarpor" class="form-control form-control-sm"
                   placeholder="{{ __('Buscar equipo') }}" value="{{ $thBuscar }}">
            <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="t-kpis">
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['equipos'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">{{ __('Equipos') }}</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['partidos'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">{{ __('Partidos') }}</div>
        </div>
        <div class="t-kpi t-kpi-acento">
            <div class="t-kpi-num">{{ number_format($kpis['goles'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">{{ __('Goles') }}</div>
        </div>
    </div>

    <div class="t-lista-filtros">
        <span class="t-lista-rot">{{ __('Torneo') }}</span>
        @foreach($thTipos as $thKey => $thRot)
            <a class="t-chip {{ $tipo == $thKey ? 't-chip-acento' : '' }}" href="{{ $thLink(['tipo' => $thKey]) }}">{{ $thRot }}</a>
        @endforeach

        <span class="t-lista-rot">{{ __('Ámbito') }}</span>
        @foreach($thAmbitos as $thKey => $thRot)
            <a class="t-chip {{ $ambito == $thKey ? 't-chip-acento' : '' }}" href="{{ $thLink(['ambito' => $thKey]) }}">{{ $thRot }}</a>
        @endforeach

        <a class="t-chip {{ $argentinos ? 't-chip-acento' : '' }}" href="{{ $thLink(['argentinos' => $argentinos ? 0 : 1]) }}">
            <i class="bi {{ $argentinos ? 'bi-check-circle-fill' : 'bi-circle' }}"></i> {{ __('Argentinos') }}
        </a>

        @if($thBuscar)
            <a class="t-chip t-chip-acento" href="{{ $thLink(['buscarpor' => '']) }}">
                <i class="bi bi-x-lg"></i> “{{ $thBuscar }}”
            </a>
        @endif
    </div>

    <div class="t-panel">
        <div class="t-tabla-wrap">
            <table class="t-tabla t-lista-tabla">
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Equipo') }}</th>

                    @foreach($thColumnas as $thKey => $thCol)
                        <th title="{{ $thCol['tit'] }}" class="{{ $order == $thKey ? 't-orden-activo' : '' }}">
                            <a href="{{ $thLink(['order' => $thKey, 'tipoOrder' => ($order == $thKey && $tipoOrder == 'DESC') ? 'ASC' : 'DESC']) }}">
                                {{ $thCol['rot'] }}
                                @if($order == $thKey)
                                    <i class="bi {{ $tipoOrder == 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                                @endif
                            </a>
                        </th>
                    @endforeach

                    <th title="{{ __('Balance de ganados, empatados y perdidos') }}">{{ __('Balance') }}</th>

                    @foreach($thColumnas2 as $thKey => $thCol)
                        <th title="{{ $thCol['tit'] }}" class="{{ $order == $thKey ? 't-orden-activo' : '' }}">
                            <a href="{{ $thLink(['order' => $thKey, 'tipoOrder' => ($order == $thKey && $tipoOrder == 'DESC') ? 'ASC' : 'DESC']) }}">
                                {{ $thCol['rot'] }}
                                @if($order == $thKey)
                                    <i class="bi {{ $tipoOrder == 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                                @endif
                            </a>
                        </th>
                    @endforeach

                    <th title="{{ __('Títulos ganados') }}">{{ __('Tít.') }}</th>
                </tr>
                </thead>

                <tbody>
                @forelse($posiciones as $equipo)
                    @php
                        $thTotalGE = $equipo->ganados + $equipo->empatados + $equipo->perdidos;
                        $thEfec    = $equipo->jugados > 0 ? round($equipo->puntaje * 100 / ($equipo->jugados * 3), 2) : 0;
                        $thTit     = titulosDesdeCadena($equipo->titulos);
                    @endphp

                    <tr>
                        <td class="t-pos">{{ $i++ }}</td>
                        <td>
                            <x-celda-equipo :href="route('equipos.ver', ['equipoId' => $equipo->equipo_id])"
                                            :nombre="$equipo->equipo"
                                            :escudo="$equipo->foto"
                                            :pais="$equipo->pais"/>
                        </td>

                        <td class="t-pts">{{ $equipo->puntaje }}</td>
                        <td>{{ $equipo->jugados }}</td>
                        <td>{{ $equipo->ganados }}</td>
                        <td>{{ $equipo->empatados }}</td>
                        <td>{{ $equipo->perdidos }}</td>

                        <td>
                            @if($thTotalGE > 0)
                                <span class="t-ge" title="{{ __(':ganadosG · :empatadosE · :perdidosP', ['ganados' => $equipo->ganados, 'empatados' => $equipo->empatados, 'perdidos' => $equipo->perdidos]) }}">
                                    <i class="g" style="width: {{ round($equipo->ganados * 100 / $thTotalGE, 1) }}%"></i>
                                    <i class="e" style="width: {{ round($equipo->empatados * 100 / $thTotalGE, 1) }}%"></i>
                                    <i class="p" style="width: {{ round($equipo->perdidos * 100 / $thTotalGE, 1) }}%"></i>
                                </span>
                            @endif
                        </td>

                        <td>{{ $equipo->golesl }}</td>
                        <td>{{ $equipo->golesv }}</td>
                        <td class="{{ $equipo->diferencia > 0 ? 't-dif-pos' : ($equipo->diferencia < 0 ? 't-dif-neg' : 't-cero') }}">
                            {{ $equipo->diferencia > 0 ? '+' : '' }}{{ $equipo->diferencia }}
                        </td>

                        <td>
                            <span class="t-lista-ef" title="{{ __(':porcentaje% de los puntos en juego', ['porcentaje' => $thEfec]) }}">
                                <b>{{ number_format($thEfec, 2, ',', '.') }}%</b>
                                <span class="t-lista-ef-pista"><i style="width: {{ min(100, max(0, $thEfec)) }}%"></i></span>
                            </span>
                        </td>

                        <td>
                            @if($thTit)
                                <span class="t-chip t-chip-acento" title="{{ $thTit['detalle'] }}">
                                    <i class="bi bi-trophy-fill"></i>{{ $thTit['total'] }}
                                </span>
                            @else
                                <span class="t-cero">–</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $thCols }}">
                            <div class="t-vacio"><i class="bi bi-shield-x"></i>{{ __('No hay equipos con esos filtros.') }}</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="t-panel-pie">
            <div>{{ trans_choice(':n equipo|:n equipos', $posiciones->total(), ['n' => number_format($posiciones->total(), 0, ',', '.')]) }}</div>
            <div class="ms-auto t-paginacion">{{ $posiciones->appends($thQuery)->links() }}</div>
        </div>
    </div>

    <div class="d-flex mt-3">
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">{{ __('Volver') }}</a>
    </div>

@endsection
