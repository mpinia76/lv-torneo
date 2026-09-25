@extends('layouts.appPublic')

@section('pageTitle', __('Arqueros'))

@section('content')

    @php
        /* Prefijo aq: $torneos, $grupo y $i están tomadas a nivel global. */
        $aqQuery = request()->except('page');
        $aqLink  = function (array $extra = []) use ($aqQuery) {
            return route('torneos.arqueros', array_merge($aqQuery, $extra));
        };

        $aqColumnas = [
            'jugados'   => ['rot' => __('PJ'),       'tit' => __('Partidos jugados')],
            'invictas'  => ['rot' => __('Invictas'), 'tit' => __('Vallas invictas')],
            'recibidos' => ['rot' => __('GC'),       'tit' => __('Goles recibidos')],
            'atajos'    => ['rot' => __('P. Atj.'),  'tit' => __('Penales atajados')],
        ];

        $aqCols   = count($aqColumnas) + 4; // #, arquero, promedio de gol recibido, equipos
        $aqBuscar = request()->get('buscarpor', session('nombre_filtro_jugador'));
    @endphp

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">{{ __('Protagonistas') }}</span>
            <h1>{{ __('Arqueros') }}</h1>
        </div>

        <form class="t-lista-busqueda" method="GET" action="{{ route('torneos.arqueros') }}">
            <input type="hidden" name="order" value="{{ $order }}">
            <input type="hidden" name="tipoOrder" value="{{ $tipoOrder }}">
            @if($actuales)<input type="hidden" name="actuales" value="1">@endif
            <input type="search" name="buscarpor" class="form-control form-control-sm"
                   placeholder="{{ __('Buscar arquero') }}" value="{{ $aqBuscar }}">
            <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="t-kpis">
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['total'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">{{ __('Arqueros') }}</div>
        </div>
        <div class="t-kpi t-kpi-win">
            <div class="t-kpi-num">{{ number_format($kpis['invictas'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">{{ __('Vallas invictas') }}</div>
        </div>
        <div class="t-kpi t-kpi-loss">
            <div class="t-kpi-num">{{ number_format($kpis['recibidos'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">{{ __('Goles recibidos') }}</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['atajos'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">{{ __('Penales atajados') }}</div>
        </div>
    </div>

    <div class="t-lista-filtros">
        <a class="t-chip {{ $actuales ? 't-chip-acento' : '' }}" href="{{ $aqLink(['actuales' => $actuales ? 0 : 1]) }}">
            <i class="bi {{ $actuales ? 'bi-check-circle-fill' : 'bi-circle' }}"></i> {{ __('Jugando') }}
        </a>
        @if($aqBuscar)
            <a class="t-chip t-chip-acento" href="{{ $aqLink(['buscarpor' => '']) }}">
                <i class="bi bi-x-lg"></i> “{{ $aqBuscar }}”
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
                    <th>{{ __('Arquero') }}</th>
                    @foreach($aqColumnas as $aqKey => $aqCol)
                        <th title="{{ $aqCol['tit'] }}" class="{{ $order == $aqKey ? 't-orden-activo' : '' }}">
                            <a href="{{ $aqLink(['order' => $aqKey, 'tipoOrder' => ($order == $aqKey && $tipoOrder == 'DESC') ? 'ASC' : 'DESC']) }}">
                                {{ $aqCol['rot'] }}
                                @if($order == $aqKey)
                                    <i class="bi {{ $tipoOrder == 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                                @endif
                            </a>
                        </th>
                    @endforeach
                    <th title="{{ __('Goles recibidos por partido') }}">{{ __('Prom.') }}</th>
                    <th class="t-izq">{{ __('Equipos') }}</th>
                </tr>
                </thead>

                <tbody>
                @forelse($arqueros as $arquero)
                    @php
                        $aqClubes = clubesDesdeCadena($arquero->escudo, ['recibidos', 'invictas']);
                        foreach ($aqClubes as $aqIdx => $aqClub) {
                            $aqClubes[$aqIdx]['dato'] = __(':gc GC · :inv invictas', ['gc' => $aqClub['recibidos'], 'inv' => $aqClub['invictas']]);
                        }
                        $aqActuales = clubesDesdeCadena($arquero->jugando);
                        $aqFilaId   = 'arq-eq-' . $arquero->id;
                        $aqInvPct   = $arquero->jugados ? round($arquero->invictas * 100 / $arquero->jugados) : 0;
                    @endphp

                    <tr>
                        <td class="t-pos">{{ $i++ }}</td>
                        <td>
                            <x-celda-persona :href="route('jugadores.ver', ['jugadorId' => $arquero->id])"
                                             :nombre="$arquero->jugador"
                                             :foto="$arquero->foto"
                                             :nacionalidad="$arquero->nacionalidad"
                                             :clubes="$aqActuales"/>
                        </td>
                        <td><a href="{{ route('jugadores.jugados', ['jugadorId' => $arquero->id]) }}">{{ $arquero->jugados }}</a></td>
                        <td class="t-pts">
                            <span class="t-lista-ef" title="{{ __(':pct% de los partidos sin goles en contra', ['pct' => $aqInvPct]) }}">
                                <b>{{ $arquero->invictas }}</b>
                                <span class="t-lista-ef-pista"><i style="width: {{ min(100, max(0, $aqInvPct)) }}%"></i></span>
                            </span>
                        </td>
                        <td>{{ $arquero->recibidos }}</td>
                        <td><a href="{{ route('jugadores.penals', ['jugadorId' => $arquero->id, 'tipo' => 'Atajó']) }}">{{ $arquero->atajos ?: '' }}</a></td>
                        <td>{{ $arquero->jugados ? number_format($arquero->recibidos / $arquero->jugados, 2, ',', '.') : '—' }}</td>
                        <td class="t-izq">
                            <x-clubes-celda :clubes="$aqClubes" :id="$aqFilaId"/>
                        </td>
                    </tr>

                    <x-clubes-detalle :clubes="$aqClubes" :id="$aqFilaId" :cols="$aqCols"/>
                @empty
                    <tr>
                        <td colspan="{{ $aqCols }}">
                            <div class="t-vacio"><i class="bi bi-person-x"></i>{{ __('No hay arqueros con esos filtros.') }}</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="t-panel-pie">
            <div>{{ trans_choice(':n arquero|:n arqueros', $arqueros->total(), ['n' => number_format($arqueros->total(), 0, ',', '.')]) }}</div>
            <div class="ms-auto t-paginacion">{{ $arqueros->appends($aqQuery)->links() }}</div>
        </div>
    </div>

    <div class="d-flex mt-3">
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">{{ __('Volver') }}</a>
    </div>

@endsection
