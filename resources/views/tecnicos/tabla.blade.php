{{--
    Carrera como técnico: tira de números + una fila por torneo.
    La incluyen tecnicos/ver y jugadores/ver (pestaña "Técnico"); en las dos
    llega $tecnico, pero los enlaces igual se protegen por las dudas.

    Variables con prefijo ft para no pisar las globales del layout
    ($torneos, $grupo, $i ya están tomadas).
--}}
@php
    $ftJugados = $ftGanados = $ftEmpatados = $ftPerdidos = 0;
    $ftFavor = $ftContra = $ftPuntaje = 0;
    $ftEquipos = [];
    $ftFilas   = [];

    foreach ($torneosTecnico as $ftT) {
        $ftJugados   += $ftT->jugados;
        $ftGanados   += $ftT->ganados;
        $ftEmpatados += $ftT->empatados;
        $ftPerdidos  += $ftT->perdidos;
        $ftFavor     += $ftT->favor;
        $ftContra    += $ftT->contra;
        $ftPuntaje   += $ftT->puntaje;

        $ftEquiposFila = [];
        foreach (explode(',', (string) $ftT->escudo) as $ftCadena) {
            if (trim($ftCadena) === '') { continue; }
            $ftPartes = explode('_', $ftCadena);
            $ftEquiposFila[] = [
                'escudo' => $ftPartes[0] ?? '',
                'id'     => $ftPartes[1] ?? '',
                'pos'    => $ftPartes[2] ?? '',
                'nombre' => $ftPartes[3] ?? '',
            ];
            if (!empty($ftPartes[1])) { $ftEquipos[$ftPartes[1]] = true; }
        }

        $ftFilas[] = ['t' => $ftT, 'equipos' => $ftEquiposFila];
    }

    $ftTitulos = $titulosTecnicoLiga + $titulosTecnicoCopa + $titulosTecnicoInternacional;
    $ftEfec    = $ftJugados > 0 ? round($ftPuntaje * 100 / ($ftJugados * 3), 1) : 0;
    $ftId      = !empty($tecnico) ? $tecnico->id : null;

    $ftCero = function ($v) {
        return $v > 0 ? e($v) : '<span class="t-cero">0</span>';
    };
    // Barra proporcional G/E/P de la fila.
    $ftBarra = function ($g, $e, $p) {
        $total = $g + $e + $p;
        if ($total <= 0) { return ''; }
        return '<span class="t-ge" title="'.$g.'G · '.$e.'E · '.$p.'P">'
            .'<i class="g" style="width:'.round($g * 100 / $total, 1).'%"></i>'
            .'<i class="e" style="width:'.round($e * 100 / $total, 1).'%"></i>'
            .'<i class="p" style="width:'.round($p * 100 / $total, 1).'%"></i></span>';
    };
@endphp

@if(count($torneosTecnico) === 0)
    <div class="t-panel"><div class="t-vacio"><i class="bi bi-clipboard-x"></i>Todavía no hay torneos cargados como técnico.</div></div>
@else

    <div class="t-kpis">
        <div class="t-kpi">
            <div class="t-kpi-num">{{ $ftJugados }}</div>
            <div class="t-kpi-rot">Dirigidos</div>
        </div>
        <div class="t-kpi t-kpi-acento">
            <div class="t-kpi-num">{{ $ftEfec }}<small>%</small></div>
            <div class="t-kpi-rot">Efectividad</div>
        </div>
        <div class="t-kpi t-kpi-win">
            <div class="t-kpi-num">{{ $ftGanados }}</div>
            <div class="t-kpi-rot">Ganados</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ $ftEmpatados }}</div>
            <div class="t-kpi-rot">Empatados</div>
        </div>
        <div class="t-kpi t-kpi-loss">
            <div class="t-kpi-num">{{ $ftPerdidos }}</div>
            <div class="t-kpi-rot">Perdidos</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ count($torneosTecnico) }}</div>
            <div class="t-kpi-rot">Torneos</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ count($ftEquipos) }}</div>
            <div class="t-kpi-rot">Equipos</div>
        </div>
        <div class="t-kpi {{ $ftTitulos > 0 ? 't-kpi-win' : 't-kpi-apagado' }}">
            <div class="t-kpi-num">{{ $ftTitulos }}</div>
            <div class="t-kpi-rot">Títulos</div>
        </div>
    </div>

    <div class="t-kpis-pie">
        @if($ftTitulos > 0)
            <span>Ligas nacionales <b>{{ $titulosTecnicoLiga }}</b></span>
            <span>Copas nacionales <b>{{ $titulosTecnicoCopa }}</b></span>
            <span>Internacionales <b>{{ $titulosTecnicoInternacional }}</b></span>
        @endif
        <span>Goles <b>{{ $ftFavor }}</b> a favor · <b>{{ $ftContra }}</b> en contra</span>
        <span class="t-referencia">{!! $ftBarra($ftGanados, $ftEmpatados, $ftPerdidos) !!} balance de la carrera</span>
    </div>

    <div class="t-panel">
        <div class="t-tabla-wrap">
            <table class="t-tabla">
                <thead>
                <tr>
                    <th>#</th>
                    <th>Torneo</th>
                    <th class="t-izq">Equipos</th>
                    <th title="Puntos">Pts</th>
                    <th title="Dirigidos">J</th>
                    <th title="Ganados">G</th>
                    <th title="Empatados">E</th>
                    <th title="Perdidos">P</th>
                    <th title="Goles a favor">GF</th>
                    <th title="Goles en contra">GC</th>
                    <th title="Diferencia de gol">Dif.</th>
                    <th title="Efectividad sobre puntos posibles">Rend.</th>
                </tr>
                </thead>
                <tbody>
                @foreach($ftFilas as $ftIndice => $ftFila)
                    @php
                        $ftT   = $ftFila['t'];
                        $ftDif = $ftT->favor - $ftT->contra;
                    @endphp
                    <tr>
                        <td class="t-pos">{{ $ftIndice + 1 }}</td>
                        <td>
                            <a class="t-torneo" href="{{ route('torneos.ver', ['torneoId' => $ftT->idTorneo]) }}">
                                <x-escudo :src="$ftT->escudoTorneo" :nombre="$ftT->nombreTorneo" tam="sm"/>
                                {{ $ftT->nombreTorneo }}
                            </a>
                        </td>
                        <td class="t-izq">
                            <span class="t-equipos-celda">
                                @foreach($ftFila['equipos'] as $ftEq)
                                    @php
                                        $ftEsSub  = strpos($ftEq['pos'], 'subcampeon.png') !== false;
                                        $ftEsCamp = !$ftEsSub && strpos($ftEq['pos'], 'campeon.png') !== false;
                                    @endphp
                                    <a class="t-equipo-chip"
                                       href="{{ route('equipos.ver', ['equipoId' => $ftEq['id']]) }}"
                                       title="{{ $ftEq['nombre'] }}">
                                        <x-escudo :src="$ftEq['escudo']" :nombre="$ftEq['nombre']" tam="sm"/>
                                        @if(trim($ftEq['pos']) !== '')
                                            <span class="t-ficha-pos {{ $ftEsCamp ? 't-campeon' : '' }}">
                                                {!! $ftEq['pos'] !!}{{ is_numeric(trim(strip_tags($ftEq['pos']))) ? '°' : '' }}
                                            </span>
                                        @endif
                                    </a>
                                @endforeach
                            </span>
                        </td>
                        <td class="t-pts">{{ $ftT->puntaje }}</td>
                        <td>
                            @if($ftId)
                                <a href="{{ route('tecnicos.jugados', ['tecnicoId' => $ftId, 'torneoId' => $ftT->idTorneo]) }}">{{ $ftT->jugados }}</a>
                            @else
                                {{ $ftT->jugados }}
                            @endif
                        </td>
                        <td>
                            @if($ftId)
                                <a href="{{ route('tecnicos.jugados', ['tecnicoId' => $ftId, 'torneoId' => $ftT->idTorneo, 'tipo' => 'Ganados']) }}">{!! $ftCero($ftT->ganados) !!}</a>
                            @else
                                {!! $ftCero($ftT->ganados) !!}
                            @endif
                        </td>
                        <td>
                            @if($ftId)
                                <a href="{{ route('tecnicos.jugados', ['tecnicoId' => $ftId, 'torneoId' => $ftT->idTorneo, 'tipo' => 'Empatados']) }}">{!! $ftCero($ftT->empatados) !!}</a>
                            @else
                                {!! $ftCero($ftT->empatados) !!}
                            @endif
                        </td>
                        <td>
                            @if($ftId)
                                <a href="{{ route('tecnicos.jugados', ['tecnicoId' => $ftId, 'torneoId' => $ftT->idTorneo, 'tipo' => 'Perdidos']) }}">{!! $ftCero($ftT->perdidos) !!}</a>
                            @else
                                {!! $ftCero($ftT->perdidos) !!}
                            @endif
                        </td>
                        <td>{!! $ftCero($ftT->favor) !!}</td>
                        <td>{!! $ftCero($ftT->contra) !!}</td>
                        <td style="color: {{ $ftDif > 0 ? 'var(--t-win)' : ($ftDif < 0 ? 'var(--t-loss)' : 'inherit') }}">
                            {{ $ftDif > 0 ? '+' : '' }}{{ $ftDif }}
                        </td>
                        <td>
                            {{ $ftT->porcentaje }}<br>
                            {!! $ftBarra($ftT->ganados, $ftT->empatados, $ftT->perdidos) !!}
                        </td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr class="t-totales">
                    <td></td>
                    <td>Totales</td>
                    <td class="t-izq">{{ count($ftEquipos) }} {{ count($ftEquipos) === 1 ? 'equipo' : 'equipos' }}</td>
                    <td class="t-pts">{{ $ftPuntaje }}</td>
                    <td>
                        @if($ftId)
                            <a href="{{ route('tecnicos.jugados', ['tecnicoId' => $ftId]) }}">{{ $ftJugados }}</a>
                        @else
                            {{ $ftJugados }}
                        @endif
                    </td>
                    <td>
                        @if($ftId)
                            <a href="{{ route('tecnicos.jugados', ['tecnicoId' => $ftId, 'tipo' => 'Ganados']) }}">{{ $ftGanados }}</a>
                        @else
                            {{ $ftGanados }}
                        @endif
                    </td>
                    <td>
                        @if($ftId)
                            <a href="{{ route('tecnicos.jugados', ['tecnicoId' => $ftId, 'tipo' => 'Empatados']) }}">{{ $ftEmpatados }}</a>
                        @else
                            {{ $ftEmpatados }}
                        @endif
                    </td>
                    <td>
                        @if($ftId)
                            <a href="{{ route('tecnicos.jugados', ['tecnicoId' => $ftId, 'tipo' => 'Perdidos']) }}">{{ $ftPerdidos }}</a>
                        @else
                            {{ $ftPerdidos }}
                        @endif
                    </td>
                    <td>{{ $ftFavor }}</td>
                    <td>{{ $ftContra }}</td>
                    <td>{{ $ftFavor - $ftContra > 0 ? '+' : '' }}{{ $ftFavor - $ftContra }}</td>
                    <td>{{ $ftEfec }}%</td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif
