@extends('layouts.appPublic')

@section('pageTitle', 'Tarjetas')

@section('content')

    @php
        /* Prefijo tj: $torneos, $grupo y $i están tomadas a nivel global. */
        $tjQuery = request()->except('page');
        $tjLink  = function (array $extra = []) use ($tjQuery) {
            return route('torneos.tarjetas', array_merge($tjQuery, $extra));
        };

        $tjColumnas = [
            'amarillas' => ['rot' => 'Amarillas', 'tit' => 'Tarjetas amarillas'],
            'rojas'     => ['rot' => 'Rojas',     'tit' => 'Tarjetas rojas (incluye doble amarilla)'],
            'jugados'   => ['rot' => 'PJ',        'tit' => 'Partidos jugados'],
        ];

        $tjCols   = count($tjColumnas) + 5; // #, jugador, prom A, prom R, equipos
        $tjBuscar = request()->get('buscarpor', session('nombre_filtro_jugador'));
    @endphp

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">Protagonistas</span>
            <h1>Tarjetas</h1>
        </div>

        <form class="t-lista-busqueda" method="GET" action="{{ route('torneos.tarjetas') }}">
            <input type="hidden" name="order" value="{{ $order }}">
            <input type="hidden" name="tipoOrder" value="{{ $tipoOrder }}">
            @if($actuales)<input type="hidden" name="actuales" value="1">@endif
            <input type="search" name="buscarpor" class="form-control form-control-sm"
                   placeholder="Buscar jugador" value="{{ $tjBuscar }}">
            <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="t-kpis">
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['total'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">Jugadores</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num t-num-amarilla">{{ number_format($kpis['amarillas'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">Amarillas</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num t-num-roja">{{ number_format($kpis['rojas'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">Rojas</div>
        </div>
    </div>

    <div class="t-lista-filtros">
        <a class="t-chip {{ $actuales ? 't-chip-acento' : '' }}" href="{{ $tjLink(['actuales' => $actuales ? 0 : 1]) }}">
            <i class="bi {{ $actuales ? 'bi-check-circle-fill' : 'bi-circle' }}"></i> Jugando
        </a>
        @if($tjBuscar)
            <a class="t-chip t-chip-acento" href="{{ $tjLink(['buscarpor' => '']) }}">
                <i class="bi bi-x-lg"></i> “{{ $tjBuscar }}”
            </a>
        @endif
        <span class="t-lista-ayuda ms-auto"><i class="bi bi-chevron-down"></i> abre el detalle por club</span>
    </div>

    <div class="t-panel">
        <div class="t-tabla-wrap">
            <table class="t-tabla t-lista-tabla">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Jugador</th>
                    @foreach($tjColumnas as $tjKey => $tjCol)
                        <th title="{{ $tjCol['tit'] }}" class="{{ $order == $tjKey ? 't-orden-activo' : '' }}">
                            <a href="{{ $tjLink(['order' => $tjKey, 'tipoOrder' => ($order == $tjKey && $tipoOrder == 'ASC') ? 'DESC' : 'ASC']) }}">
                                {{ $tjCol['rot'] }}
                                @if($order == $tjKey)
                                    <i class="bi {{ $tipoOrder == 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                                @endif
                            </a>
                        </th>
                    @endforeach
                    <th title="Amarillas por partido">Prom. A</th>
                    <th title="Rojas por partido">Prom. R</th>
                    <th class="t-izq">Equipos</th>
                </tr>
                </thead>

                <tbody>
                @forelse($tarjetas as $jugador)
                    @php
                        $tjClubes = clubesDesdeCadena($jugador->escudo, ['rojas', 'amarillas']);
                        foreach ($tjClubes as $tjIdx => $tjClub) {
                            $tjPartes = [];
                            if ((int) $tjClub['amarillas'] > 0) { $tjPartes[] = $tjClub['amarillas'].((int) $tjClub['amarillas'] == 1 ? ' amarilla' : ' amarillas'); }
                            if ((int) $tjClub['rojas'] > 0)     { $tjPartes[] = $tjClub['rojas'].((int) $tjClub['rojas'] == 1 ? ' roja' : ' rojas'); }
                            $tjClubes[$tjIdx]['dato'] = implode(' · ', $tjPartes);
                        }
                        $tjActuales = clubesDesdeCadena($jugador->jugando);
                        $tjFilaId   = 'tar-eq-' . $jugador->id;
                    @endphp

                    <tr>
                        <td class="t-pos">{{ $i++ }}</td>
                        <td>
                            <x-celda-persona :href="route('jugadores.ver', ['jugadorId' => $jugador->id])"
                                             :nombre="$jugador->jugador"
                                             :foto="$jugador->foto"
                                             :nacionalidad="$jugador->nacionalidad"
                                             :clubes="$tjActuales"/>
                        </td>
                        <td class="t-pts">
                            <a href="{{ route('jugadores.tarjetas', ['jugadorId' => $jugador->id, 'tipo' => 'Amarilla']) }}">
                                <span class="t-tarjeta-a"></span> {{ $jugador->amarillas }}
                            </a>
                        </td>
                        <td class="t-pts">
                            <a href="{{ route('jugadores.tarjetas', ['jugadorId' => $jugador->id, 'tipo' => 'Roja']) }}">
                                <span class="t-tarjeta-r"></span> {{ $jugador->rojas }}
                            </a>
                        </td>
                        <td><a href="{{ route('jugadores.jugados', ['jugadorId' => $jugador->id]) }}">{{ $jugador->jugados }}</a></td>
                        <td>{{ $jugador->jugados ? number_format($jugador->amarillas / $jugador->jugados, 2, ',', '.') : '—' }}</td>
                        <td>{{ $jugador->jugados ? number_format($jugador->rojas / $jugador->jugados, 2, ',', '.') : '—' }}</td>
                        <td class="t-izq">
                            <x-clubes-celda :clubes="$tjClubes" :id="$tjFilaId"/>
                        </td>
                    </tr>

                    <x-clubes-detalle :clubes="$tjClubes" :id="$tjFilaId" :cols="$tjCols"/>
                @empty
                    <tr>
                        <td colspan="{{ $tjCols }}">
                            <div class="t-vacio"><i class="bi bi-person-x"></i>No hay jugadores con esos filtros.</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="t-panel-pie">
            <div>{{ number_format($tarjetas->total(), 0, ',', '.') }} jugadores</div>
            <div class="ms-auto t-paginacion">{{ $tarjetas->appends($tjQuery)->links() }}</div>
        </div>
    </div>

    <div class="d-flex mt-3">
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">Volver</a>
    </div>

@endsection
