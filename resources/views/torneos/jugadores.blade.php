@extends('layouts.appPublic')

@section('pageTitle', 'Jugadores')

@section('content')

    @php
        /* Prefijo jg: $torneos, $grupo y $i están tomadas a nivel global. */
        $jgQuery = request()->except('page');
        $jgLink  = function (array $extra = []) use ($jgQuery) {
            return route('torneos.jugadores', array_merge($jgQuery, $extra));
        };

        $jgColumnas = [
            'jugados'   => ['rot' => 'PJ',    'tit' => 'Partidos jugados'],
            'titulos'   => ['rot' => 'Tít.',  'tit' => 'Títulos ganados'],
            'Goles'     => ['rot' => 'Goles', 'tit' => 'Goles convertidos'],
            'amarillas' => ['rot' => 'Amar.', 'tit' => 'Tarjetas amarillas'],
            'rojas'     => ['rot' => 'Rojas', 'tit' => 'Tarjetas rojas'],
            'errados'   => ['rot' => 'P. Err.', 'tit' => 'Penales errados'],
            'atajos'    => ['rot' => 'P. Atj.', 'tit' => 'Penales atajados (arqueros)'],
            'recibidos' => ['rot' => 'GC',    'tit' => 'Goles recibidos (arqueros)'],
            'invictas'  => ['rot' => 'Inv.',  'tit' => 'Vallas invictas (arqueros)'],
        ];

        $jgCols   = count($jgColumnas) + 3; // #, jugador, equipos
        $jgBuscar = request()->get('buscarpor', session('nombre_filtro_jugador'));
        $jgCero   = function ($v) { return $v > 0 ? $v : '<span class="t-cero">0</span>'; };
    @endphp

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">Protagonistas</span>
            <h1>Jugadores</h1>
        </div>

        <form class="t-lista-busqueda" method="GET" action="{{ route('torneos.jugadores') }}">
            <input type="hidden" name="order" value="{{ $order }}">
            <input type="hidden" name="tipoOrder" value="{{ $tipoOrder }}">
            @if($actuales)<input type="hidden" name="actuales" value="1">@endif
            <input type="search" name="buscarpor" class="form-control form-control-sm"
                   placeholder="Buscar jugador" value="{{ $jgBuscar }}">
            <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="t-kpis">
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['total'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">Jugadores</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['jugados'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">Partidos jugados</div>
        </div>
        <div class="t-kpi t-kpi-acento">
            <div class="t-kpi-num">{{ number_format($kpis['goles'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">Goles</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['titulos'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">Títulos</div>
        </div>
    </div>

    <div class="t-lista-filtros">
        <a class="t-chip {{ $actuales ? 't-chip-acento' : '' }}" href="{{ $jgLink(['actuales' => $actuales ? 0 : 1]) }}">
            <i class="bi {{ $actuales ? 'bi-check-circle-fill' : 'bi-circle' }}"></i> Jugando
        </a>
        @if($jgBuscar)
            <a class="t-chip t-chip-acento" href="{{ $jgLink(['buscarpor' => '']) }}">
                <i class="bi bi-x-lg"></i> “{{ $jgBuscar }}”
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
                    @foreach($jgColumnas as $jgKey => $jgCol)
                        <th title="{{ $jgCol['tit'] }}" class="{{ $order == $jgKey ? 't-orden-activo' : '' }}">
                            <a href="{{ $jgLink(['order' => $jgKey, 'tipoOrder' => ($order == $jgKey && $tipoOrder == 'ASC') ? 'DESC' : 'ASC']) }}">
                                {{ $jgCol['rot'] }}
                                @if($order == $jgKey)
                                    <i class="bi {{ $tipoOrder == 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                                @endif
                            </a>
                        </th>
                    @endforeach
                    <th class="t-izq">Equipos</th>
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
                            <div class="t-vacio"><i class="bi bi-person-x"></i>No hay jugadores con esos filtros.</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="t-panel-pie">
            <div>{{ number_format($jugadores->total(), 0, ',', '.') }} jugadores</div>
            <div class="ms-auto t-paginacion">{{ $jugadores->appends($jgQuery)->links() }}</div>
        </div>
    </div>

    <div class="d-flex mt-3">
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">Volver</a>
    </div>

@endsection
