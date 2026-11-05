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
            $ftPartes = partesEscudo($ftCadena);
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

    $ftSeleccion = $titulosTecnicoSeleccion ?? 0;
    $ftTitulos = $titulosTecnicoLiga + $titulosTecnicoCopa + $titulosTecnicoInternacional + $ftSeleccion;
    $ftEfec    = $ftJugados > 0 ? round($ftPuntaje * 100 / ($ftJugados * 3), 1) : 0;
    $ftId      = !empty($tecnico) ? $tecnico->id : null;

    $ftCero = function ($v) {
        return $v > 0 ? e($v) : '<span class="t-cero">0</span>';
    };
    // Barra proporcional G/E/P de la fila.
    $ftBarra = function ($g, $e, $p) {
        $total = $g + $e + $p;
        if ($total <= 0) { return ''; }
        return '<span class="t-ge" title="'.$g.__('G').' · '.$e.__('E').' · '.$p.__('P').'">'
            .'<i class="g" style="width:'.round($g * 100 / $total, 1).'%"></i>'
            .'<i class="e" style="width:'.round($e * 100 / $total, 1).'%"></i>'
            .'<i class="p" style="width:'.round($p * 100 / $total, 1).'%"></i></span>';
    };
@endphp

@if(count($torneosTecnico) === 0)
    <div class="t-panel"><div class="t-vacio"><i class="bi bi-clipboard-x"></i>{{ __('Todavía no hay torneos cargados como técnico.') }}</div></div>
@else

    <div class="t-kpis">
        <div class="t-kpi">
            <div class="t-kpi-num">{{ $ftJugados }}</div>
            <div class="t-kpi-rot">{{ __('Dirigidos') }}</div>
        </div>
        <div class="t-kpi t-kpi-acento">
            <div class="t-kpi-num">{{ $ftEfec }}<small>%</small></div>
            <div class="t-kpi-rot">{{ __('Efectividad') }}</div>
        </div>
        <div class="t-kpi t-kpi-win">
            <div class="t-kpi-num">{{ $ftGanados }}</div>
            <div class="t-kpi-rot">{{ __('Ganados') }}</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ $ftEmpatados }}</div>
            <div class="t-kpi-rot">{{ __('Empatados') }}</div>
        </div>
        <div class="t-kpi t-kpi-loss">
            <div class="t-kpi-num">{{ $ftPerdidos }}</div>
            <div class="t-kpi-rot">{{ __('Perdidos') }}</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ count($torneosTecnico) }}</div>
            <div class="t-kpi-rot">{{ __('Torneos') }}</div>
        </div>
        <div class="t-kpi">
            <div class="t-kpi-num">{{ count($ftEquipos) }}</div>
            <div class="t-kpi-rot">{{ __('Equipos') }}</div>
        </div>
        <div class="t-kpi {{ $ftTitulos > 0 ? 't-kpi-win' : 't-kpi-apagado' }}">
            <div class="t-kpi-num">{{ $ftTitulos }}</div>
            <div class="t-kpi-rot">{{ __('Títulos') }}</div>
        </div>
    </div>

    <div class="t-kpis-pie">
        @if($ftTitulos > 0)
            <span>{{ __('Ligas nacionales') }} <b>{{ $titulosTecnicoLiga }}</b></span>
            <span>{{ __('Copas nacionales') }} <b>{{ $titulosTecnicoCopa }}</b></span>
            <span>{{ __('Internacionales') }} <b>{{ $titulosTecnicoInternacional }}</b></span>
            @if($ftSeleccion > 0)
                <span>{{ __('Con selección') }} <b>{{ $ftSeleccion }}</b></span>
            @endif
        @endif
        <span>{!! __('Goles :favor a favor · :contra en contra', ['favor' => '<b>'.e($ftFavor).'</b>', 'contra' => '<b>'.e($ftContra).'</b>']) !!}</span>
        <span class="t-referencia">{!! $ftBarra($ftGanados, $ftEmpatados, $ftPerdidos) !!} {{ __('balance de la carrera') }}</span>
        @foreach(['invicto' => __('Mayor invicto'), 'victorias' => __('Más victorias seguidas')] as $ftRTipo => $ftRRot)
            @php $ftR = ($rachasDt ?? [])[$ftRTipo] ?? null; @endphp
            @if($ftR)
                <span title="{{ $ftR['club'] }} · {{ date('d/m/Y', strtotime($ftR['desde'])) }} – {{ date('d/m/Y', strtotime($ftR['hasta'])) }}">
                    {{ $ftRRot }} <b>{{ $ftR['n'] }}</b> <span class="t-referencia">({{ $ftR['club'] }}, {{ substr($ftR['desde'], 0, 4) === substr($ftR['hasta'], 0, 4) ? substr($ftR['desde'], 0, 4) : substr($ftR['desde'], 0, 4).'–'.substr($ftR['hasta'], 0, 4) }})</span>
                </span>
            @endif
        @endforeach
    </div>

    {{-- Los totales salen solo de lo cargado; la base todavía no tiene la historia completa. --}}
    <p class="t-alcance"><i class="bi bi-info-circle"></i> {{ __('Totales según los torneos cargados en La Planilla.') }}</p>

    <div class="t-panel">
        <div class="t-tabla-wrap">
            <table class="t-tabla">
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Torneo') }}</th>
                    <th class="t-izq">{{ __('Equipos') }}</th>
                    <th title="{{ __('Puntos') }}">{{ __('Pts') }}</th>
                    <th title="{{ __('Dirigidos') }}">{{ __('J') }}</th>
                    <th title="{{ __('Ganados') }}">{{ __('G') }}</th>
                    <th title="{{ __('Empatados') }}">{{ __('E') }}</th>
                    <th title="{{ __('Perdidos') }}">{{ __('P') }}</th>
                    <th title="{{ __('Goles a favor') }}">{{ __('GF') }}</th>
                    <th title="{{ __('Goles en contra') }}">{{ __('GC') }}</th>
                    <th title="{{ __('Diferencia de gol') }}">{{ __('Dif.') }}</th>
                    <th title="{{ __('Efectividad sobre puntos posibles') }}">{{ __('Rend.') }}</th>
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
                    <td>{{ __('Totales') }}</td>
                    <td class="t-izq">{{ trans_choice(':n equipo|:n equipos', count($ftEquipos), ['n' => count($ftEquipos)]) }}</td>
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

    {{-- Lo que sigue lo arma TecnicoController@ver; en la ficha del jugador (pestaña técnico) no viene. --}}
    @php
        $ftEfecDe = function ($f) {
            return $f->pj > 0 ? round(($f->g * 3 + $f->e) * 100 / ($f->pj * 3), 1) . '%' : '–';
        };
        $ftPeriodo = function ($f) {
            $d = substr((string) $f->desde, 0, 4);
            $h = substr((string) $f->hasta, 0, 4);
            return $d === $h ? $d : $d . '–' . $h;
        };
    @endphp

    {{-- Por club y, aparte, por selección (como en Transfermarkt). Los
         clubes desde dos (con uno solo repetiría el total); la selección,
         siempre que haya. --}}
    @php
        $ftBloques = [
            ['titulo' => __('Por club'),  'filas' => $porClubDt ?? [],      'minimo' => 2],
            ['titulo' => __('Selección'), 'filas' => $porSeleccionDt ?? [], 'minimo' => 1],
        ];
    @endphp
    @foreach($ftBloques as $ftBloque)
    @if(count($ftBloque['filas']) >= $ftBloque['minimo'])
        <h2 class="t-seccion">{{ $ftBloque['titulo'] }}</h2>
        <div class="t-panel">
            <div class="t-tabla-wrap">
                <table class="t-tabla">
                    <thead>
                    <tr>
                        <th class="t-izq">{{ __('Equipo') }}</th>
                        <th>{{ __('Período') }}</th>
                        <th title="{{ __('Partidos dirigidos') }}">{{ __('PJ') }}</th>
                        <th title="{{ __('Ganados') }}">{{ __('G') }}</th>
                        <th title="{{ __('Empatados') }}">{{ __('E') }}</th>
                        <th title="{{ __('Perdidos') }}">{{ __('P') }}</th>
                        <th></th>
                        <th title="{{ __('Goles a favor') }}">{{ __('GF') }}</th>
                        <th title="{{ __('Goles en contra') }}">{{ __('GC') }}</th>
                        <th title="{{ __('Efectividad') }}">%</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($ftBloque['filas'] as $ftClub)
                        <tr>
                            <td class="t-izq">
                                <x-celda-equipo :href="route('equipos.ver', ['equipoId' => $ftClub->equipo_id])"
                                                :nombre="$ftClub->nombre" :escudo="$ftClub->escudo" :pais="$ftClub->pais" tam="sm"/>
                            </td>
                            <td>{{ $ftPeriodo($ftClub) }}</td>
                            <td class="t-pts">{{ $ftClub->pj }}</td>
                            <td>{!! $ftCero($ftClub->g) !!}</td>
                            <td>{!! $ftCero($ftClub->e) !!}</td>
                            <td>{!! $ftCero($ftClub->p) !!}</td>
                            <td>{!! $ftBarra((int) $ftClub->g, (int) $ftClub->e, (int) $ftClub->p) !!}</td>
                            <td>{{ $ftClub->gf }}</td>
                            <td>{{ $ftClub->gc }}</td>
                            <td>{{ $ftEfecDe($ftClub) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    @endforeach

    @if(!empty($rivalesDt ?? []))
        <h2 class="t-seccion">{{ __('Rivales más enfrentados') }}</h2>
        <div class="t-panel">
            <div class="t-tabla-wrap">
                <table class="t-tabla">
                    <thead>
                    <tr>
                        <th class="t-izq">{{ __('Rival') }}</th>
                        <th title="{{ __('Partidos dirigidos') }}">{{ __('PJ') }}</th>
                        <th title="{{ __('Ganados') }}">{{ __('G') }}</th>
                        <th title="{{ __('Empatados') }}">{{ __('E') }}</th>
                        <th title="{{ __('Perdidos') }}">{{ __('P') }}</th>
                        <th></th>
                        <th title="{{ __('Goles a favor') }}">{{ __('GF') }}</th>
                        <th title="{{ __('Goles en contra') }}">{{ __('GC') }}</th>
                        <th title="{{ __('Efectividad') }}">%</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($rivalesDt as $ftRival)
                        <tr>
                            <td class="t-izq">
                                <x-celda-equipo :href="route('equipos.ver', ['equipoId' => $ftRival->equipo_id])"
                                                :nombre="$ftRival->nombre" :escudo="$ftRival->escudo" :pais="$ftRival->pais" tam="sm"/>
                            </td>
                            <td class="t-pts">{{ $ftRival->pj }}</td>
                            <td>{!! $ftCero($ftRival->g) !!}</td>
                            <td>{!! $ftCero($ftRival->e) !!}</td>
                            <td>{!! $ftCero($ftRival->p) !!}</td>
                            <td>{!! $ftBarra((int) $ftRival->g, (int) $ftRival->e, (int) $ftRival->p) !!}</td>
                            <td>{{ $ftRival->gf }}</td>
                            <td>{{ $ftRival->gc }}</td>
                            <td>{{ $ftEfecDe($ftRival) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if(!empty($goleadoresDt ?? []) || !empty($usadosDt ?? []))
        <div class="row">
            @if(!empty($goleadoresDt ?? []))
                <div class="col-md-6">
                    <h2 class="t-seccion">{{ __('Goleadores con él') }}</h2>
                    <div class="t-panel">
                        <div class="t-tabla-wrap">
                            <table class="t-tabla">
                                <thead><tr><th>#</th><th class="t-izq">{{ __('Jugador') }}</th><th>{{ __('Goles') }}</th></tr></thead>
                                <tbody>
                                @foreach($goleadoresDt as $ftI => $ftGol)
                                    <tr>
                                        <td class="t-pos">{{ $ftI + 1 }}</td>
                                        <td class="t-izq">
                                            <x-celda-persona :href="route('jugadores.ver', ['jugadorId' => $ftGol->jugador_id])"
                                                             :nombre="$ftGol->nombre" :foto="$ftGol->foto" :nacionalidad="$ftGol->nacionalidad"/>
                                        </td>
                                        <td class="t-pts">{{ $ftGol->goles }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
            @if(!empty($usadosDt ?? []))
                <div class="col-md-6">
                    <h2 class="t-seccion">{{ __('Los que más usó') }}</h2>
                    <div class="t-panel">
                        <div class="t-tabla-wrap">
                            <table class="t-tabla">
                                <thead><tr><th>#</th><th class="t-izq">{{ __('Jugador') }}</th>
                                    <th title="{{ __('Partidos jugados') }}">{{ __('J') }}</th>
                                    <th title="{{ __('Como titular') }}">{{ __('Tit.') }}</th></tr></thead>
                                <tbody>
                                @foreach($usadosDt as $ftI => $ftUso)
                                    <tr>
                                        <td class="t-pos">{{ $ftI + 1 }}</td>
                                        <td class="t-izq">
                                            <x-celda-persona :href="route('jugadores.ver', ['jugadorId' => $ftUso->jugador_id])"
                                                             :nombre="$ftUso->nombre" :foto="$ftUso->foto" :nacionalidad="$ftUso->nacionalidad"/>
                                        </td>
                                        <td class="t-pts">{{ $ftUso->jugados }}</td>
                                        <td>{{ $ftUso->titular }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if(!empty($debutDt ?? null))
        <h2 class="t-seccion">{{ !empty($ultimoDt ?? null) ? __('Debut y último partido') : __('Debut') }}</h2>
        <div class="t-panel t-lista-partidos">
            <x-partido :p="$debutDt"><span class="t-chip">{{ __('Debut') }}</span></x-partido>
            @if(!empty($ultimoDt ?? null))
                <x-partido :p="$ultimoDt"><span class="t-chip">{{ __('Último partido') }}</span></x-partido>
            @endif
        </div>
    @endif
@endif
