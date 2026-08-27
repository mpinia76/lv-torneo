@extends('layouts.appPublic')

@section('pageTitle', 'Títulos')

@section('content')

    @php
        /* Prefijo tt: $torneos, $grupo y $i están tomadas a nivel global. */
        $ttQuery = request()->except('page');
        $ttLink  = function (array $extra = []) use ($ttQuery) {
            return route('torneos.titulos', array_merge($ttQuery, $extra));
        };

        $ttColumnas = [
            'titulos'         => ['rot' => 'Títulos',         'tit' => 'Total de títulos'],
            'ligas'           => ['rot' => 'Ligas',           'tit' => 'Ligas nacionales'],
            'copas'           => ['rot' => 'Copas',           'tit' => 'Copas nacionales'],
            'internacionales' => ['rot' => 'Internacionales', 'tit' => 'Títulos internacionales'],
        ];

        $ttCols   = count($ttColumnas) + 3; // #, equipo, reparto
        $ttBuscar = request()->get('buscarpor', session('nombre_filtro_equipo'));
        $ttCero   = function ($v) { return $v > 0 ? $v : '<span class="t-cero">0</span>'; };
    @endphp

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">Equipos</span>
            <h1>Títulos</h1>
        </div>

        <form class="t-lista-busqueda" method="GET" action="{{ route('torneos.titulos') }}">
            <input type="hidden" name="order" value="{{ $order }}">
            <input type="hidden" name="tipoOrder" value="{{ $tipoOrder }}">
            @if($argentinos)<input type="hidden" name="argentinos" value="1">@endif
            <input type="search" name="buscarpor" class="form-control form-control-sm"
                   placeholder="Buscar equipo" value="{{ $ttBuscar }}">
            <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-search"></i></button>
        </form>
    </div>

    <div class="t-kpis">
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['equipos'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">Equipos campeones</div>
        </div>
        <div class="t-kpi t-kpi-acento">
            <div class="t-kpi-num">{{ number_format($kpis['titulos'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">Títulos</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['ligas'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">Ligas</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['copas'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">Copas</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ number_format($kpis['internacionales'], 0, ',', '.') }}</div>
            <div class="t-kpi-rot">Internacionales</div>
        </div>
    </div>

    <div class="t-lista-filtros">
        <a class="t-chip {{ $argentinos ? 't-chip-acento' : '' }}" href="{{ $ttLink(['argentinos' => $argentinos ? 0 : 1]) }}">
            <i class="bi {{ $argentinos ? 'bi-check-circle-fill' : 'bi-circle' }}"></i> Argentinos
        </a>
        @if($ttBuscar)
            <a class="t-chip t-chip-acento" href="{{ $ttLink(['buscarpor' => '']) }}">
                <i class="bi bi-x-lg"></i> “{{ $ttBuscar }}”
            </a>
        @endif
        <span class="t-lista-ayuda ms-auto">
            <span class="t-referencia"><i class="t-ref-liga"></i> Ligas</span>
            <span class="t-referencia"><i class="t-ref-copa"></i> Copas</span>
            <span class="t-referencia"><i class="t-ref-inter"></i> Internacionales</span>
        </span>
    </div>

    <div class="t-panel">
        <div class="t-tabla-wrap">
            <table class="t-tabla t-lista-tabla">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Equipo</th>
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
                    <th title="Cómo se reparten los títulos">Reparto</th>
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
                        <td>{!! $ttCero($equipo->ligas) !!}</td>
                        <td>{!! $ttCero($equipo->copas) !!}</td>
                        <td>{!! $ttCero($equipo->internacionales) !!}</td>

                        <td>
                            @if($ttTotal > 0)
                                <span class="t-reparto"
                                      title="{{ $equipo->ligas }} ligas · {{ $equipo->copas }} copas · {{ $equipo->internacionales }} internacionales">
                                    <i class="l" style="width: {{ round($equipo->ligas * 100 / $ttTotal, 1) }}%"></i>
                                    <i class="c" style="width: {{ round($equipo->copas * 100 / $ttTotal, 1) }}%"></i>
                                    <i class="n" style="width: {{ round($equipo->internacionales * 100 / $ttTotal, 1) }}%"></i>
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $ttCols }}">
                            <div class="t-vacio"><i class="bi bi-trophy"></i>No hay equipos con esos filtros.</div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="t-panel-pie">
            <div>{{ number_format($posiciones->total(), 0, ',', '.') }} equipos</div>
            <div class="ms-auto t-paginacion">{{ $posiciones->appends($ttQuery)->links() }}</div>
        </div>
    </div>

    <div class="d-flex mt-3">
        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm">Volver</a>
    </div>

@endsection
