@extends('layouts.appPublic')

@section('pageTitle', __('Jugadores'))

@section('content')

    @php
        /* Prefijo jg: $torneos, $grupo y $i están tomadas a nivel global. */
        $jgQuery = request()->except('page');
        $jgLink  = function (array $extra = []) use ($jgQuery) {
            return route('torneos.jugadores', array_merge($jgQuery, $extra));
        };

        $jgColumnas = [
            'jugados'   => ['rot' => __('PJ'),    'tit' => __('Partidos jugados')],
            'titulos'   => ['rot' => __('Tít.'),  'tit' => __('Títulos ganados')],
            'Goles'     => ['rot' => __('Goles'), 'tit' => __('Goles convertidos')],
            'amarillas' => ['rot' => __('Amar.'), 'tit' => __('Tarjetas amarillas')],
            'rojas'     => ['rot' => __('Rojas'), 'tit' => __('Tarjetas rojas')],
            'errados'   => ['rot' => __('P. Err.'), 'tit' => __('Penales errados')],
            'atajos'    => ['rot' => __('P. Atj.'), 'tit' => __('Penales atajados (arqueros)')],
            'recibidos' => ['rot' => __('GC'),    'tit' => __('Goles recibidos (arqueros)')],
            'invictas'  => ['rot' => __('Inv.'),  'tit' => __('Vallas invictas (arqueros)')],
        ];

        $jgCols   = count($jgColumnas) + 3; // #, jugador, equipos
        $jgBuscar = request()->get('buscarpor', session('nombre_filtro_jugador'));
        $jgCero   = function ($v) { return $v > 0 ? $v : '<span class="t-cero">0</span>'; };
    @endphp

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">{{ __('Protagonistas') }}</span>
            <h1>{{ __('Jugadores') }}</h1>
        </div>

        <form class="t-lista-busqueda" method="GET" action="{{ route('torneos.jugadores') }}">
            <input type="hidden" name="order" value="{{ $order }}">
            <input type="hidden" name="tipoOrder" value="{{ $tipoOrder }}">
            @if($actuales)<input type="hidden" name="actuales" value="1">@endif
            <input type="search" name="buscarpor" class="form-control form-control-sm"
                   placeholder="{{ __('Buscar jugador') }}" value="{{ $jgBuscar }}">
            <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="t-kpis">
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['total'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">{{ __('Jugadores') }}</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['jugados'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">{{ __('Partidos jugados') }}</div>
        </div>
        <div class="t-kpi t-kpi-acento">
            <div class="t-kpi-num">{{ number_format($kpis['goles'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">{{ __('Goles') }}</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['titulos'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">{{ __('Títulos') }}</div>
        </div>
    </div>

    <div class="t-lista-filtros">
        <a class="t-chip {{ $actuales ? 't-chip-acento' : '' }}" href="{{ $jgLink(['actuales' => $actuales ? 0 : 1]) }}">
            <i class="bi {{ $actuales ? 'bi-check-circle-fill' : 'bi-circle' }}"></i> {{ __('Jugando') }}
        </a>
        @if($jgBuscar)
            <a class="t-chip t-chip-acento" href="{{ $jgLink(['buscarpor' => '']) }}">
                <i class="bi bi-x-lg"></i> “{{ $jgBuscar }}”
            </a>
        @endif
        <span class="t-lista-ayuda ms-auto"><i class="bi bi-chevron-down"></i> {{ __('abre el detalle por club') }}</span>
    </div>

    <div class="t-panel">
        <div class="t-tabla-wrap">
            <table class="t-tabla t-lista-tabla">
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Jugador') }}</th>
                    @foreach($jgColumnas as $jgKey => $jgCol)
                        <th title="{{ $jgCol['tit'] }}" class="{{ $order == $jgKey ? 't-orden-activo' : '' }}">
                            <a href="{{ $jgLink(['order' => $jgKey, 'tipoOrder' => ($order == $jgKey && $tipoOrder == 'DESC') ? 'ASC' : 'DESC']) }}">
                                {{ $jgCol['rot'] }}
                                @if($order == $jgKey)
                                    <i class="bi {{ $tipoOrder == 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                                @endif
                            </a>
                        </th>
                    @endforeach
                    <th class="t-izq">{{ __('Equipos') }}</th>
                </tr>
                </thead>

                <tbody>
                @forelse($jugadores as $jugador)
                    @php
                        $jgClubes = collect(clubesDesdeCadena($jugador->escudo))->unique('id')->values()->all();
                        $jgActuales = collect(clubesDesdeCadena($jugador->jugando))->unique('id')->values()->all();
                        $jgFilaId = 'jug-eq-' . $jugador->jugador_id;
                    @endphp

                    <tr>
                        <td class="t-pos">{{ $i++ }}</td>
                        <td>
                            <x-celda-persona :href="route('jugadores.ver', ['jugadorId' => $jugador->jugador_id])"
                                             :nombre="$jugador->jugador"
                                             :foto="$jugador->foto"
                                             :nacionalidad="$jugador->nacionalidad"
                                             :clubes="$jgActuales"/>
                        </td>
                        <td class="t-pts"><a href="{{ route('jugadores.jugados', ['jugadorId' => $jugador->jugador_id]) }}">{{ $jugador->jugados }}</a></td>
                        <td>
                            @if($jugador->titulos > 0)
                                <a href="{{ route('jugadores.titulos', ['jugadorId' => $jugador->jugador_id]) }}">
                                    <span class="t-chip t-chip-acento"><i class="bi bi-trophy-fill"></i>{{ $jugador->titulos }}</span>
                                </a>
                            @else
                                <span class="t-cero">–</span>
                            @endif
                        </td>
                        <td><a href="{{ route('jugadores.goles', ['jugadorId' => $jugador->jugador_id]) }}">{!! $jgCero($jugador->goles) !!}</a></td>
                        <td><a href="{{ route('jugadores.tarjetas', ['jugadorId' => $jugador->jugador_id, 'tipo' => 'Amarilla']) }}">{!! $jgCero($jugador->amarillas) !!}</a></td>
                        <td><a href="{{ route('jugadores.tarjetas', ['jugadorId' => $jugador->jugador_id, 'tipo' => 'Rojas']) }}">{!! $jgCero($jugador->rojas) !!}</a></td>
                        <td><a href="{{ route('jugadores.penals', ['jugadorId' => $jugador->jugador_id]) }}">{!! $jgCero($jugador->errados) !!}</a></td>
                        <td><a href="{{ route('jugadores.penals', ['jugadorId' => $jugador->jugador_id, 'tipo' => 'Atajó']) }}">{!! $jgCero($jugador->atajos) !!}</a></td>
                        <td>{!! $jgCero($jugador->recibidos) !!}</td>
                        <td>{!! $jgCero($jugador->invictas) !!}</td>
                        <td class="t-izq">
                            <x-clubes-celda :clubes="$jgClubes" :id="$jgFilaId"/>
                        </td>
                    </tr>

                    <x-clubes-detalle :clubes="$jgClubes" :id="$jgFilaId" :cols="$jgCols"/>
                @empty
                    <tr>
                        <td colspan="{{ $jgCols }}">
                            <div class="t-vacio"><i class="bi bi-person-x"></i>{{ __('No hay jugadores con esos filtros.') }}</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="t-panel-pie">
            <div>{{ trans_choice(':n jugador|:n jugadores', $jugadores->total(), ['n' => number_format($jugadores->total(), 0, ',', '.')]) }}</div>
            <div class="ms-auto t-paginacion">{{ $jugadores->appends($jgQuery)->links() }}</div>
        </div>
    </div>

    <div class="d-flex mt-3">
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">{{ __('Volver') }}</a>
    </div>

@endsection
