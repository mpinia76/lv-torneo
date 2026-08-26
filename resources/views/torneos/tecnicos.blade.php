@extends('layouts.appPublic')

@section('pageTitle', 'Técnicos')

@section('content')

    @php
        /* Variables con prefijo tc: $torneos, $grupo y $i estan tomadas a nivel global. */

        // Parametros vigentes, para que ordenar o paginar no pierda los filtros.
        $tcQuery = request()->except('page');

        $tcLink = function (array $extra = []) use ($tcQuery) {
            return route('torneos.tecnicos', array_merge($tcQuery, $extra));
        };

        // "3 (2 Ligas 1 Copas)" -> total + detalle legible para el tooltip.
        $tcTitulos = function ($cadena) {
            $cadena = trim((string) $cadena);
            if ($cadena === '') { return null; }

            $total   = (int) $cadena;
            $detalle = '';

            if (preg_match('/^(\d+)\s*\((.*)\)$/u', $cadena, $m)) {
                $total   = (int) $m[1];
                $detalle = $m[2];
            }

            $detalle = str_replace(
                ['1 Ligas', '1 Copas', '1 Internacionales'],
                ['1 Liga', '1 Copa', '1 Internacional'],
                $detalle
            );
            $detalle = preg_replace('/(\p{L})\s+(?=\d)/u', '$1 · ', $detalle);

            return ['total' => $total, 'detalle' => $detalle];
        };

        // El controlador arma "escudo_id_pts_%_nombre[_titulos]" separado por comas.
        // El nombre puede traer espacios, asi que se lee por los extremos.
        $tcEquipos = function ($cadena) {
            $filas = [];

            foreach (explode(',', (string) $cadena) as $item) {
                if (trim($item) === '') { continue; }

                $partes  = explode('_', $item);
                $escudo  = array_shift($partes);
                $id      = array_shift($partes);
                $pts     = array_shift($partes);
                $pct     = array_shift($partes);
                $titulos = '';

                if (count($partes) > 1 && preg_match('/^\d+\s*\(/u', (string) end($partes))) {
                    $titulos = array_pop($partes);
                }

                $filas[] = [
                    'escudo'  => $escudo,
                    'id'      => $id,
                    'pts'     => (int) $pts,
                    'pct'     => rtrim((string) $pct, '%'),
                    'nombre'  => implode('_', $partes),
                    'titulos' => $titulos,
                ];
            }

            return $filas;
        };

        // "escudo_id_nombre" de los equipos que dirige este año.
        $tcDirige = function ($cadena) {
            $filas = [];

            foreach (explode(',', (string) $cadena) as $item) {
                if (trim($item) === '') { continue; }

                $partes = explode('_', $item);
                $escudo = array_shift($partes);
                $id     = array_shift($partes);

                $filas[$id] = [
                    'escudo' => $escudo,
                    'id'     => $id,
                    'nombre' => implode('_', $partes),
                ];
            }

            return $filas;
        };

        $tcColumnas = [
            'puntaje'    => ['rot' => 'Pts',  'tit' => 'Puntos'],
            'jugados'    => ['rot' => 'J',    'tit' => 'Dirigidos'],
            'ganados'    => ['rot' => 'G',    'tit' => 'Ganados'],
            'empatados'  => ['rot' => 'E',    'tit' => 'Empatados'],
            'perdidos'   => ['rot' => 'P',    'tit' => 'Perdidos'],
        ];

        $tcColumnas2 = [
            'golesl'     => ['rot' => 'GF',   'tit' => 'Goles a favor'],
            'golesv'     => ['rot' => 'GC',   'tit' => 'Goles en contra'],
            'diferencia' => ['rot' => 'Dif.', 'tit' => 'Diferencia de gol'],
            'prom'       => ['rot' => '%',    'tit' => 'Efectividad sobre puntos en juego'],
        ];

        $tcTotalCols = count($tcColumnas) + count($tcColumnas2) + 5; // #, técnico, balance, títulos, equipos
        $tcVisibles  = 6; // escudos que entran en la fila antes del "+N"
    @endphp

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">Protagonistas</span>
            <h1>Técnicos</h1>
        </div>

        <form class="t-tec-busqueda" method="GET" action="{{ route('torneos.tecnicos') }}">
            <input type="hidden" name="order" value="{{ $order }}">
            <input type="hidden" name="tipoOrder" value="{{ $tipoOrder }}">
            @if($actuales)<input type="hidden" name="actuales" value="1">@endif
            @if($campeones)<input type="hidden" name="campeones" value="1">@endif

            <input type="search" name="buscarpor" class="form-control form-control-sm"
                   placeholder="Buscar técnico"
                   value="{{ request()->get('buscarpor', session('nombre_filtro_jugador')) }}">
            <button class="btn btn-outline-secondary btn-sm" type="submit">
                <i class="bi bi-search"></i>
            </button>
        </form>
    </div>

    {{-- Tira de números --}}
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

    {{-- Filtros activos --}}
    <div class="t-tec-filtros">
        <a class="t-chip {{ $actuales ? 't-chip-acento' : '' }}"
           href="{{ $tcLink(['actuales' => $actuales ? 0 : 1]) }}">
            <i class="bi {{ $actuales ? 'bi-check-circle-fill' : 'bi-circle' }}"></i> Dirigiendo
        </a>
        <a class="t-chip {{ $campeones ? 't-chip-acento' : '' }}"
           href="{{ $tcLink(['campeones' => $campeones ? 0 : 1]) }}">
            <i class="bi {{ $campeones ? 'bi-check-circle-fill' : 'bi-circle' }}"></i> Campeones
        </a>

        @if(request()->get('buscarpor', session('nombre_filtro_jugador')))
            <a class="t-chip t-chip-acento" href="{{ $tcLink(['buscarpor' => '']) }}">
                <i class="bi bi-x-lg"></i> “{{ request()->get('buscarpor', session('nombre_filtro_jugador')) }}”
            </a>
        @endif

        <span class="t-tec-ayuda ms-auto">
            <i class="bi bi-chevron-down"></i> abre el detalle por club
        </span>
    </div>

    <div class="t-panel">
        <div class="t-tabla-wrap">
            <table class="t-tabla t-tec-tabla">
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
                        $tcEqs     = $tcEquipos($tecnico->escudo);
                        $tcActual  = $tcDirige($tecnico->jugando);
                        $tcTit     = $tcTitulos($tecnico->titulos);
                        $tcResto   = max(0, count($tcEqs) - $tcVisibles);
                        $tcFilaId  = 'tec-eq-' . $tecnico->tecnico_id;
                        $tcTotalGE = $tecnico->ganados + $tecnico->empatados + $tecnico->perdidos;
                        $tcEfec    = (float) rtrim((string) $tecnico->porcentaje, '%');
                    @endphp

                    <tr @if($tcActual) class="t-tec-dirigiendo" @endif>
                        <td class="t-pos">{{ $i++ }}</td>

                        <td>
                            <span class="t-persona-celda">
                                <a href="{{ route('tecnicos.ver', ['tecnicoId' => $tecnico->tecnico_id]) }}">
                                    <img src="{{ url('images/'.($tecnico->fotoTecnico ?: 'sin_foto_tecnico.png')) }}"
                                         alt="{{ $tecnico->tecnico }}" loading="lazy">
                                </a>
                                <span class="t-tec-txt">
                                    <span class="t-tec-nombre">
                                        <a href="{{ route('tecnicos.ver', ['tecnicoId' => $tecnico->tecnico_id]) }}">{{ $tecnico->tecnico }}</a>
                                        @if($tecnico->nacionalidadTecnico)
                                            <img class="bandera"
                                                 src="{{ url('images/'.removeAccents($tecnico->nacionalidadTecnico).'.gif') }}"
                                                 alt="{{ $tecnico->nacionalidadTecnico }}"
                                                 title="{{ $tecnico->nacionalidadTecnico }}">
                                        @endif
                                    </span>
                                    @if($tcActual)
                                        <span class="t-tec-sub">
                                            <span class="t-tec-punto"></span> Dirige
                                            @foreach($tcActual as $tcEq)
                                                <a href="{{ route('equipos.ver', ['equipoId' => $tcEq['id']]) }}">
                                                    <x-escudo :src="$tcEq['escudo']" :nombre="$tcEq['nombre']" tam="sm"/>
                                                </a>
                                            @endforeach
                                        </span>
                                    @endif
                                </span>
                            </span>
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
                            <span class="t-tec-ef" title="{{ $tecnico->porcentaje }} de los puntos en juego">
                                <b>{{ $tecnico->porcentaje }}</b>
                                <span class="t-tec-ef-pista"><i style="width: {{ min(100, max(0, $tcEfec)) }}%"></i></span>
                            </span>
                        </td>

                        <td>
                            @if($tcTit && $tcTit['total'] > 0)
                                <span class="t-chip t-chip-acento" title="{{ $tcTit['detalle'] }}">
                                    <i class="bi bi-trophy-fill"></i>{{ $tcTit['total'] }}
                                </span>
                            @else
                                <span class="t-cero">–</span>
                            @endif
                        </td>

                        <td class="t-izq">
                            @if(count($tcEqs))
                                <span class="t-tec-escudos">
                                    @foreach(array_slice($tcEqs, 0, $tcVisibles) as $tcEq)
                                        <a href="{{ route('equipos.ver', ['equipoId' => $tcEq['id']]) }}">
                                            <x-escudo :src="$tcEq['escudo']"
                                                      :nombre="$tcEq['nombre'].' · '.$tcEq['pts'].' pts · '.$tcEq['pct'].'%'"/>
                                        </a>
                                    @endforeach

                                    <button type="button" class="t-tec-mas" data-tec-abre="{{ $tcFilaId }}"
                                            aria-expanded="false" aria-controls="{{ $tcFilaId }}"
                                            title="Ver el detalle por club">
                                        @if($tcResto > 0)
                                            +{{ $tcResto }}
                                        @else
                                            <i class="bi bi-chevron-down"></i>
                                        @endif
                                    </button>
                                </span>
                            @endif
                        </td>
                    </tr>

                    @if(count($tcEqs))
                        <tr class="t-tec-detalle" id="{{ $tcFilaId }}" hidden>
                            <td colspan="{{ $tcTotalCols }}">
                                <div class="t-tec-clubes">
                                    @foreach($tcEqs as $tcEq)
                                        @php $tcTitEq = $tcTitulos($tcEq['titulos']); @endphp
                                        <a class="t-tec-club" href="{{ route('equipos.ver', ['equipoId' => $tcEq['id']]) }}">
                                            <x-escudo :src="$tcEq['escudo']" :nombre="$tcEq['nombre']"/>
                                            <span class="t-tec-club-nom">{{ $tcEq['nombre'] }}</span>
                                            <span class="t-tec-club-dato">{{ $tcEq['pts'] }} pts · {{ $tcEq['pct'] }}%</span>
                                            @if($tcTitEq && $tcTitEq['total'] > 0)
                                                <span class="t-chip t-chip-acento" title="{{ $tcTitEq['detalle'] }}">
                                                    <i class="bi bi-trophy-fill"></i>{{ $tcTitEq['total'] }}
                                                </span>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="{{ $tcTotalCols }}">
                            <div class="t-vacio">
                                <i class="bi bi-person-x"></i>
                                No hay técnicos con esos filtros.
                            </div>
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

    <script>
        // Abre y cierra la fila con el detalle por club.
        document.querySelectorAll('[data-tec-abre]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                var fila = document.getElementById(boton.dataset.tecAbre);
                if (!fila) { return; }

                var abierta = !fila.hidden;
                fila.hidden = abierta;
                boton.classList.toggle('activo', !abierta);
                boton.setAttribute('aria-expanded', String(!abierta));
            });
        });
    </script>

@endsection
