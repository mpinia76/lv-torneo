{{--
    Carrera como jugador: tira de números + una fila por torneo.
    La incluyen jugadores/ver y tecnicos/ver, así que NO puede depender de
    $jugador (en la ficha del técnico esa variable no existe): los enlaces a
    los listados van dentro de @isset.

    Variables con prefijo fj para no pisar las globales del layout
    ($torneos, $grupo, $i ya están tomadas).
--}}
@php
    $fjJugados = $fjGoles = $fjAmarillas = $fjRojas = 0;
    $fjErrados = $fjAtajados = $fjRecibidos = $fjInvictas = 0;
    $fjEquipos = [];
    $fjFilas   = [];

    foreach ($torneosJugador as $fjT) {
        $fjJugados   += $fjT->jugados;
        $fjGoles     += $fjT->goles;
        $fjAmarillas += $fjT->amarillas;
        $fjRojas     += $fjT->rojas;
        $fjErrados   += $fjT->errados;
        $fjAtajados  += $fjT->atajados;
        $fjRecibidos += $fjT->recibidos;
        $fjInvictas  += $fjT->invictas;

        $fjEquiposFila = [];
        foreach (explode(',', (string) $fjT->escudo) as $fjCadena) {
            if (trim($fjCadena) === '') { continue; }
            $fjPartes = partesEscudo($fjCadena);
            $fjEquiposFila[] = [
                'escudo' => $fjPartes[0] ?? '',
                'id'     => $fjPartes[1] ?? '',
                'pos'    => $fjPartes[2] ?? '',
                'nombre' => $fjPartes[3] ?? '',
            ];
            if (!empty($fjPartes[1])) { $fjEquipos[$fjPartes[1]] = true; }
        }

        $fjFilas[] = ['t' => $fjT, 'equipos' => $fjEquiposFila];
    }

    $fjTitulos = $titulosJugadorLiga + $titulosJugadorCopa + $titulosJugadorInternacional;
    $fjProm    = $fjJugados > 0 ? number_format($fjGoles / $fjJugados, 2) : '0.00';

    // Columnas de arquero y de penales: sólo si hay algo que mostrar.
    $fjArco    = ($fjAtajados + $fjRecibidos + $fjInvictas) > 0;
    $fjPenales = ($fjErrados + $fjAtajados) > 0;

    $fjCero = function ($v) {
        return $v > 0 ? e($v) : '<span class="t-cero">0</span>';
    };
    $fjRatio = function ($v, $sobre) {
        return $sobre > 0 && $v > 0 ? '<span class="t-prom">('.number_format($v / $sobre, 2).')</span>' : '';
    };
@endphp

@if(count($torneosJugador) === 0)
    <div class="t-panel"><div class="t-vacio"><i class="bi bi-clipboard-x"></i>{{ __('Todavía no hay torneos cargados como jugador.') }}</div></div>
@else

    <div class="t-kpis">
        <div class="t-kpi">
            <div class="t-kpi-num">{{ $fjJugados }}</div>
            <div class="t-kpi-rot">{{ __('Partidos') }}</div>
        </div>
        <div class="t-kpi t-kpi-acento">
            <div class="t-kpi-num">{{ $fjGoles }}</div>
            <div class="t-kpi-rot">{{ __('Goles') }}</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ $fjProm }}</div>
            <div class="t-kpi-rot">{{ __('Gol/partido') }}</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ count($torneosJugador) }}</div>
            <div class="t-kpi-rot">{{ __('Torneos') }}</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ count($fjEquipos) }}</div>
            <div class="t-kpi-rot">{{ __('Equipos') }}</div>
        </div>
        <div class="t-kpi {{ $fjTitulos > 0 ? 't-kpi-win' : 't-kpi-apagado' }}">
            <div class="t-kpi-num">{{ $fjTitulos }}</div>
            <div class="t-kpi-rot">{{ __('Títulos') }}</div>
        </div>
        @if($fjArco)
            <div class="t-kpi">
                <div class="t-kpi-num">{{ $fjInvictas }}</div>
                <div class="t-kpi-rot">{{ __('Vallas invictas') }}</div>
            </div>
        @endif
    </div>

    @if($fjTitulos > 0)
        <div class="t-kpis-pie">
            <span>{{ __('Ligas nacionales') }} <b>{{ $titulosJugadorLiga }}</b></span>
            <span>{{ __('Copas nacionales') }} <b>{{ $titulosJugadorCopa }}</b></span>
            <span>{{ __('Internacionales') }} <b>{{ $titulosJugadorInternacional }}</b></span>
        </div>
    @endif

    <div class="t-panel">
        <div class="t-tabla-wrap">
            <table class="t-tabla">
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Torneo') }}</th>
                    <th class="t-izq">{{ __('Equipos') }}</th>
                    <th title="{{ __('Partidos jugados') }}">{{ __('J') }}</th>
                    <th title="{{ __('Goles') }}">{{ __('Goles') }}</th>
                    <th title="{{ __('Tarjetas amarillas') }}">{{ __('TA') }}</th>
                    <th title="{{ __('Tarjetas rojas') }}">{{ __('TR') }}</th>
                    @if($fjPenales)
                        <th title="{{ __('Penales errados') }}">{{ __('P. Err.') }}</th>
                    @endif
                    @if($fjArco)
                        <th title="{{ __('Penales atajados') }}">{{ __('P. Ataj.') }}</th>
                        <th title="{{ __('Goles recibidos') }}">{{ __('GC') }}</th>
                        <th title="{{ __('Vallas invictas') }}">{{ __('VI') }}</th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @foreach($fjFilas as $fjIndice => $fjFila)
                    @php $fjT = $fjFila['t']; @endphp
                    <tr>
                        <td class="t-pos">{{ $fjIndice + 1 }}</td>
                        <td>
                            <a class="t-torneo" href="{{ route('torneos.ver', ['torneoId' => $fjT->idTorneo]) }}">
                                <x-escudo :src="$fjT->escudoTorneo" :nombre="$fjT->nombreTorneo" tam="sm"/>
                                {{ $fjT->nombreTorneo }}
                            </a>
                        </td>
                        <td class="t-izq">
                            <span class="t-equipos-celda">
                                @foreach($fjFila['equipos'] as $fjEq)
                                    @php
                                        $fjEsSub  = strpos($fjEq['pos'], 'subcampeon.png') !== false;
                                        $fjEsCamp = !$fjEsSub && strpos($fjEq['pos'], 'campeon.png') !== false;
                                    @endphp
                                    <a class="t-equipo-chip"
                                       href="{{ route('equipos.ver', ['equipoId' => $fjEq['id']]) }}"
                                       title="{{ $fjEq['nombre'] }}">
                                        <x-escudo :src="$fjEq['escudo']" :nombre="$fjEq['nombre']" tam="sm"/>
                                        @if(trim($fjEq['pos']) !== '')
                                            <span class="t-ficha-pos {{ $fjEsCamp ? 't-campeon' : '' }}">
                                                {!! $fjEq['pos'] !!}{{ is_numeric(trim(strip_tags($fjEq['pos']))) ? '°' : '' }}
                                            </span>
                                        @endif
                                    </a>
                                @endforeach
                            </span>
                        </td>
                        <td class="t-pts">
                            @isset($jugador)
                                <a href="{{ route('jugadores.jugados', ['jugadorId' => $jugador->id, 'torneoId' => $fjT->idTorneo]) }}">{{ $fjT->jugados }}</a>
                            @else
                                {{ $fjT->jugados }}
                            @endisset
                        </td>
                        <td>
                            @isset($jugador)
                                <a href="{{ route('jugadores.goles', ['jugadorId' => $jugador->id, 'torneoId' => $fjT->idTorneo]) }}">{!! $fjCero($fjT->goles) !!}</a>
                            @else
                                {!! $fjCero($fjT->goles) !!}
                            @endisset
                            {!! $fjRatio($fjT->goles, $fjT->jugados) !!}
                        </td>
                        <td>{!! $fjCero($fjT->amarillas) !!}</td>
                        <td>{!! $fjCero($fjT->rojas) !!}</td>
                        @if($fjPenales)
                            <td>{!! $fjCero($fjT->errados) !!}</td>
                        @endif
                        @if($fjArco)
                            <td>{!! $fjCero($fjT->atajados) !!}</td>
                            <td>{!! $fjCero($fjT->recibidos) !!} {!! $fjRatio($fjT->recibidos, $fjT->jugados) !!}</td>
                            <td>{!! $fjCero($fjT->invictas) !!}</td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr class="t-totales">
                    <td></td>
                    <td>{{ __('Totales') }}</td>
                    <td class="t-izq">{{ trans_choice(':n equipo|:n equipos', count($fjEquipos), ['n' => count($fjEquipos)]) }}</td>
                    <td class="t-pts">{{ $fjJugados }}</td>
                    <td>{{ $fjGoles }} {!! $fjRatio($fjGoles, $fjJugados) !!}</td>
                    <td>{{ $fjAmarillas }}</td>
                    <td>{{ $fjRojas }}</td>
                    @if($fjPenales)
                        <td>{{ $fjErrados }}</td>
                    @endif
                    @if($fjArco)
                        <td>{{ $fjAtajados }}</td>
                        <td>{{ $fjRecibidos }} {!! $fjRatio($fjRecibidos, $fjJugados) !!}</td>
                        <td>{{ $fjInvictas }}</td>
                    @endif
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif
