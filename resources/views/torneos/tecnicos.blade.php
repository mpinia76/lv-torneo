@extends('layouts.appPublic')

@section('pageTitle', 'Técnicos')

@section('content')

    @php
        /* Prefijo tc: $torneos, $grupo y $i están tomadas a nivel global. */
        $tcQuery = request()->except('page');
        $tcLink  = function (array $extra = []) use ($tcQuery) {
            return route('torneos.tecnicos', array_merge($tcQuery, $extra));
        };

        $tcColumnas = [
            'puntaje'   => ['rot' => 'Pts', 'tit' => 'Puntos'],
            'jugados'   => ['rot' => 'J',   'tit' => 'Dirigidos'],
            'ganados'   => ['rot' => 'G',   'tit' => 'Ganados'],
            'empatados' => ['rot' => 'E',   'tit' => 'Empatados'],
            'perdidos'  => ['rot' => 'P',   'tit' => 'Perdidos'],
        ];

        $tcColumnas2 = [
            'golesl'     => ['rot' => 'GF',   'tit' => 'Goles a favor'],
            'golesv'     => ['rot' => 'GC',   'tit' => 'Goles en contra'],
            'diferencia' => ['rot' => 'Dif.', 'tit' => 'Diferencia de gol'],
            'prom'       => ['rot' => '%',    'tit' => 'Efectividad sobre puntos en juego'],
        ];

        $tcCols   = count($tcColumnas) + count($tcColumnas2) + 5; // #, técnico, balance, títulos, equipos
        $tcBuscar = request()->get('buscarpor', session('nombre_filtro_jugador'));
    @endphp

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">Protagonistas</span>
            <h1>Técnicos</h1>
        </div>

        <form class="t-lista-busqueda" method="GET" action="{{ route('torneos.tecnicos') }}">
            <input type="hidden" name="order" value="{{ $order }}">
            <input type="hidden" name="tipoOrder" value="{{ $tipoOrder }}">
            @if($actuales)<input type="hidden" name="actuales" value="1">@endif
            @if($campeones)<input type="hidden" name="campeones" value="1">@endif
            <input type="search" name="buscarpor" class="form-control form-control-sm"
                   placeholder="Buscar técnico" value="{{ $tcBuscar }}">
            <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="t-kpis">
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($totalTecnicos, 0, ',', '.') }}</div>
            <div class="t-kpi-rot">Técnicos</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($totalPartidos, 0, ',', '.') }}</div>
            <div class="t-kpi-rot">Partidos dirigidos</div>
        </div>
        <a class="t-kpi t-kpi-enlace {{ $actuales ? 't-kpi-acento' : '' }}"
           href="{{ $tcLink(['actuales' => $actuales ? 0 : 1]) }}">
            <div class="t-kpi-num">{{ number_format($totalDirigiendo, 0, ',', '.') }}</div>
            <div class="t-kpi-rot">Dirigiendo en {{ $year }}</div>
        </a>
        <a class="t-kpi t-kpi-enlace {{ $campeones ? 't-kpi-acento' : '' }}"
           href="{{ $tcLink(['campeones' => $campeones ? 0 : 1]) }}">
            <div class="t-kpi-num">{{ number_format($totalCampeones, 0, ',', '.') }}</div>
            <div class="t-kpi-rot">Campeones</div>
        </a>
    </div>

    <div class="t-lista-filtros">
        <a class="t-chip {{ $actuales ? 't-chip-acento' : '' }}" href="{{ $tcLink(['actuales' => $actuales ? 0 : 1]) }}">
            <i class="bi {{ $actuales ? 'bi-check-circle-fill' : 'bi-circle' }}"></i> Dirigiendo
        </a>
        <a class="t-chip {{ $campeones ? 't-chip-acento' : '' }}" href="{{ $tcLink(['campeones' => $campeones ? 0 : 1]) }}">
            <i class="bi {{ $campeones ? 'bi-check-circle-fill' : 'bi-circle' }}"></i> Campeones
        </a>
        @if($tcBuscar)
            <a class="t-chip t-chip-acento" href="{{ $tcLink(['buscarpor' => '']) }}">
                <i class="bi bi-x-lg"></i> “{{ $tcBuscar }}”
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
                    <th>Técnico</th>

                    @foreach($tcColumnas as $tcKey => $tcCol)
                        <th title="{{ $tcCol['tit'] }}" class="{{ $order == $tcKey ? 't-orden-activo' : '' }}">
                            <a href="{{ $tcLink(['order' => $tcKey, 'tipoOrder' => ($order == $tcKey && $tipoOrder == 'ASC') ? 'DESC' : 'ASC']) }}">
                                {{ $tcCol['rot'] }}
                                @if($order == $tcKey)
                                    <i class="bi {{ $tipoOrder == 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                                @endif
                            </a>
                        </th>
                    @endforeach

                    <th title="Balance de ganados, empatados y perdidos">Balance</th>

                    @foreach($tcColumnas2 as $tcKey => $tcCol)
                        <th title="{{ $tcCol['tit'] }}" class="{{ $order == $tcKey ? 't-orden-activo' : '' }}">
                            <a href="{{ $tcLink(['order' => $tcKey, 'tipoOrder' => ($order == $tcKey && $tipoOrder == 'ASC') ? 'DESC' : 'ASC']) }}">
                                {{ $tcCol['rot'] }}
                                @if($order == $tcKey)
                                    <i class="bi {{ $tipoOrder == 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                                @endif
                            </a>
                        </th>
                    @endforeach

                    <th title="Títulos ganados como técnico">Tít.</th>
                    <th class="t-izq">Equipos</th>
                </tr>
                </thead>

                <tbody>
                @forelse($goleadores as $tecnico)
                    @php
                        $tcClubes = clubesDesdeCadena($tecnico->escudo, ['pts', 'pct'], true);
                        foreach ($tcClubes as $tcIdx => $tcClub) {
                            $tcClubes[$tcIdx]['dato'] = $tcClub['pts'].' pts · '.rtrim($tcClub['pct'], '%').'%';
                        }
                        $tcActuales = clubesDesdeCadena($tecnico->jugando);
                        $tcTit      = titulosDesdeCadena($tecnico->titulos);
                        $tcFilaId   = 'tec-eq-' . $tecnico->tecnico_id;
                        $tcTotalGE  = $tecnico->ganados + $tecnico->empatados + $tecnico->perdidos;
                        $tcEfec     = (float) rtrim((string) $tecnico->porcentaje, '%');
                    @endphp

                    <tr>
                        <td class="t-pos">{{ $i++ }}</td>
                        <td>
                            <x-celda-persona :href="route('tecnicos.ver', ['tecnicoId' => $tecnico->tecnico_id])"
                                             :nombre="$tecnico->tecnico"
                                             :foto="$tecnico->fotoTecnico"
                                             fotoDefecto="sin_foto_tecnico.png"
                                             :nacionalidad="$tecnico->nacionalidadTecnico"
                                             rot="Dirige"
                                             :clubes="$tcActuales"/>
                        </td>

                        <td class="t-pts">{{ $tecnico->puntaje }}</td>
                        <td><a href="{{ route('tecnicos.jugados', ['tecnicoId' => $tecnico->tecnico_id]) }}">{{ $tecnico->jugados }}</a></td>
                        <td><a href="{{ route('tecnicos.jugados', ['tecnicoId' => $tecnico->tecnico_id, 'tipo' => 'Ganados']) }}">{{ $tecnico->ganados }}</a></td>
                        <td><a href="{{ route('tecnicos.jugados', ['tecnicoId' => $tecnico->tecnico_id, 'tipo' => 'Empatados']) }}">{{ $tecnico->empatados }}</a></td>
                        <td><a href="{{ route('tecnicos.jugados', ['tecnicoId' => $tecnico->tecnico_id, 'tipo' => 'Perdidos']) }}">{{ $tecnico->perdidos }}</a></td>

                        <td>
                            @if($tcTotalGE > 0)
                                <span class="t-ge" title="{{ $tecnico->ganados }}G · {{ $tecnico->empatados }}E · {{ $tecnico->perdidos }}P">
                                    <i class="g" style="width: {{ round($tecnico->ganados * 100 / $tcTotalGE, 1) }}%"></i>
                                    <i class="e" style="width: {{ round($tecnico->empatados * 100 / $tcTotalGE, 1) }}%"></i>
                                    <i class="p" style="width: {{ round($tecnico->perdidos * 100 / $tcTotalGE, 1) }}%"></i>
                                </span>
                            @endif
                        </td>

                        <td>{{ $tecnico->golesl }}</td>
                        <td>{{ $tecnico->golesv }}</td>
                        <td class="{{ $tecnico->diferencia > 0 ? 't-dif-pos' : ($tecnico->diferencia < 0 ? 't-dif-neg' : 't-cero') }}">
                            {{ $tecnico->diferencia > 0 ? '+' : '' }}{{ $tecnico->diferencia }}
                        </td>

                        <td>
                            <span class="t-lista-ef" title="{{ $tecnico->porcentaje }} de los puntos en juego">
                                <b>{{ $tecnico->porcentaje }}</b>
                                <span class="t-lista-ef-pista"><i style="width: {{ min(100, max(0, $tcEfec)) }}%"></i></span>
                            </span>
                        </td>

                        <td>
                            @if($tcTit)
                                <span class="t-chip t-chip-acento" title="{{ $tcTit['detalle'] }}">
                                    <i class="bi bi-trophy-fill"></i>{{ $tcTit['total'] }}
                                </span>
                            @else
                                <span class="t-cero">–</span>
                            @endif
                        </td>

                        <td class="t-izq">
                            <x-clubes-celda :clubes="$tcClubes" :id="$tcFilaId"/>
                        </td>
                    </tr>

                    <x-clubes-detalle :clubes="$tcClubes" :id="$tcFilaId" :cols="$tcCols"/>
                @empty
                    <tr>
                        <td colspan="{{ $tcCols }}">
                            <div class="t-vacio"><i class="bi bi-person-x"></i>No hay técnicos con esos filtros.</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="t-panel-pie">
            <div>{{ number_format($goleadores->total(), 0, ',', '.') }} técnicos</div>
            <div class="ms-auto t-paginacion">{{ $goleadores->appends($tcQuery)->links() }}</div>
        </div>
    </div>

    <div class="d-flex mt-3">
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">Volver</a>
    </div>

@endsection
