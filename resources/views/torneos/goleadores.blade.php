@extends('layouts.appPublic')

@section('pageTitle', __('Goleadores'))

@section('content')

    @php
        /* Prefijo gl: $torneos, $grupo y $i están tomadas a nivel global. */
        $glQuery = request()->except('page');
        $glLink  = function (array $extra = []) use ($glQuery) {
            return route('torneos.goleadores', array_merge($glQuery, $extra));
        };

        $glColumnas = [
            'goles'      => ['rot' => __('Goles'),     'tit' => __('Goles convertidos')],
            'Jugada'     => ['rot' => __('Jugada'),    'tit' => __('De jugada')],
            'Cabeza'     => ['rot' => __('Cabeza'),    'tit' => __('De cabeza')],
            'Penal'      => ['rot' => __('Penal'),     'tit' => __('De penal')],
            'Tiro_Libre' => ['rot' => __('T. Libre'),  'tit' => __('De tiro libre')],
            'Olimpico'   => ['rot' => __('Olímp.'),    'tit' => __('Olímpicos: directo desde el córner')],
            'jugados'    => ['rot' => __('PJ'),        'tit' => __('Partidos jugados')],
        ];

        $glCols   = count($glColumnas) + 4; // #, jugador, promedio, equipos
        $glBuscar = request()->get('buscarpor', session('nombre_filtro_jugador'));
    @endphp

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">{{ __('Protagonistas') }}</span>
            <h1>{{ __('Goleadores') }}</h1>
        </div>

        <form class="t-lista-busqueda" method="GET" action="{{ route('torneos.goleadores') }}">
            <input type="hidden" name="order" value="{{ $order }}">
            <input type="hidden" name="tipoOrder" value="{{ $tipoOrder }}">
            @if($actuales)<input type="hidden" name="actuales" value="1">@endif
            <input type="search" name="buscarpor" class="form-control form-control-sm"
                   placeholder="{{ __('Buscar jugador') }}" value="{{ $glBuscar }}">
            <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="t-kpis">
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['total'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">{{ __('Goleadores') }}</div>
        </div>
        <div class="t-kpi t-kpi-acento">
            <div class="t-kpi-num">{{ number_format($kpis['goles'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">{{ __('Goles') }}</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['cabeza'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">{{ __('De cabeza') }}</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['penal'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">{{ __('De penal') }}</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['olimpico'] ?? 0, 0, ',', '.') }}</div>
            <div class="t-kpi-rot">{{ __('Olímpicos') }}</div>
        </div>
    </div>

    <div class="t-lista-filtros">
        <a class="t-chip {{ $actuales ? 't-chip-acento' : '' }}" href="{{ $glLink(['actuales' => $actuales ? 0 : 1]) }}">
            <i class="bi {{ $actuales ? 'bi-check-circle-fill' : 'bi-circle' }}"></i> {{ __('Jugando') }}
        </a>
        @if($glBuscar)
            <a class="t-chip t-chip-acento" href="{{ $glLink(['buscarpor' => '']) }}">
                <i class="bi bi-x-lg"></i> “{{ $glBuscar }}”
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
                    @foreach($glColumnas as $glKey => $glCol)
                        <th title="{{ $glCol['tit'] }}" class="{{ $order == $glKey ? 't-orden-activo' : '' }}">
                            <a href="{{ $glLink(['order' => $glKey, 'tipoOrder' => ($order == $glKey && $tipoOrder == 'DESC') ? 'ASC' : 'DESC']) }}">
                                {{ $glCol['rot'] }}
                                @if($order == $glKey)
                                    <i class="bi {{ $tipoOrder == 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                                @endif
                            </a>
                        </th>
                    @endforeach
                    <th title="{{ __('Goles por partido') }}">{{ __('Prom.') }}</th>
                    <th class="t-izq">{{ __('Equipos') }}</th>
                </tr>
                </thead>

                <tbody>
                @forelse($goleadores as $jugador)
                    @php
                        $glClubes = clubesDesdeCadena($jugador->escudo, ['goles']);
                        foreach ($glClubes as $glIdx => $glClub) {
                            $glClubes[$glIdx]['dato'] = trans_choice(':n gol|:n goles', $glClub['goles'], ['n' => $glClub['goles']]);
                        }
                        $glActuales = clubesDesdeCadena($jugador->jugando);
                        $glFilaId   = 'gol-eq-' . $jugador->id;
                    @endphp

                    <tr>
                        <td class="t-pos">{{ $i++ }}</td>
                        <td>
                            <x-celda-persona :href="route('jugadores.ver', ['jugadorId' => $jugador->id])"
                                             :nombre="$jugador->jugador"
                                             :foto="$jugador->foto"
                                             :nacionalidad="$jugador->nacionalidad"
                                             :clubes="$glActuales"/>
                        </td>
                        <td class="t-pts"><a href="{{ route('jugadores.goles', ['jugadorId' => $jugador->id]) }}">{{ $jugador->goles }}</a></td>
                        <td><a href="{{ route('jugadores.goles', ['jugadorId' => $jugador->id, 'tipo' => 'Jugada']) }}">{{ $jugador->Jugada ?: '' }}</a></td>
                        <td><a href="{{ route('jugadores.goles', ['jugadorId' => $jugador->id, 'tipo' => 'Cabeza']) }}">{{ $jugador->Cabeza ?: '' }}</a></td>
                        <td><a href="{{ route('jugadores.goles', ['jugadorId' => $jugador->id, 'tipo' => 'Penal']) }}">{{ $jugador->Penal ?: '' }}</a></td>
                        <td><a href="{{ route('jugadores.goles', ['jugadorId' => $jugador->id, 'tipo' => 'Tiro Libre']) }}">{{ $jugador->Tiro_Libre ?: '' }}</a></td>
                        <td><a href="{{ route('jugadores.goles', ['jugadorId' => $jugador->id, 'tipo' => 'Olímpico']) }}">{{ $jugador->Olimpico ?: '' }}</a></td>
                        <td><a href="{{ route('jugadores.jugados', ['jugadorId' => $jugador->id]) }}">{{ $jugador->jugados }}</a></td>
                        <td>{{ $jugador->jugados ? number_format($jugador->goles / $jugador->jugados, 2, ',', '.') : '—' }}</td>
                        <td class="t-izq">
                            <x-clubes-celda :clubes="$glClubes" :id="$glFilaId"/>
                        </td>
                    </tr>

                    <x-clubes-detalle :clubes="$glClubes" :id="$glFilaId" :cols="$glCols"/>
                @empty
                    <tr>
                        <td colspan="{{ $glCols }}">
                            <div class="t-vacio"><i class="bi bi-person-x"></i>{{ __('No hay goleadores con esos filtros.') }}</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="t-panel-pie">
            <div>{{ trans_choice(':n jugador|:n jugadores', $goleadores->total(), ['n' => number_format($goleadores->total(), 0, ',', '.')]) }}</div>
            <div class="ms-auto t-paginacion">{{ $goleadores->appends($glQuery)->links() }}</div>
        </div>
    </div>

    <div class="d-flex mt-3">
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">{{ __('Volver') }}</a>
    </div>

@endsection
