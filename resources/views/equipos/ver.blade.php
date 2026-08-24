@extends('layouts.appPublic')

@section('pageTitle', $equipo->nombre)

@section('content')
    @php
        // Prefijo ve para no pisar las globales del layout ($torneos, $grupo, $i).
        $vePest = request()->get('pestActiva') ?: 'historia';

        $veJugados = $veGanados = $veEmpatados = $vePerdidos = 0;
        $veFavor = $veContra = $vePuntaje = 0;
        foreach ($torneosEquipo as $veT) {
            $veJugados   += (int) $veT->jugados;
            $veGanados   += (int) $veT->ganados;
            $veEmpatados += (int) $veT->empatados;
            $vePerdidos  += (int) $veT->perdidos;
            $veFavor     += (int) $veT->favor;
            $veContra    += (int) $veT->contra;
            $vePuntaje   += (int) $veT->puntaje;
        }
        $veEfec    = $veJugados > 0 ? round($vePuntaje * 100 / ($veJugados * 3), 1) : 0;
        $veTitulos = $titulosLiga + $titulosCopa + $titulosInternacional;

        $veFundado = $equipo->fundacion && $equipo->fundacion != '0000-00-00'
            ? \Carbon\Carbon::parse($equipo->fundacion)
            : null;

        $veCero = function ($v) {
            return $v > 0 ? e($v) : '<span class="t-cero">0</span>';
        };
        // Barra proporcional G/E/P.
        $veBarra = function ($g, $e, $p) {
            $total = $g + $e + $p;
            if ($total <= 0) { return ''; }
            return '<span class="t-ge" title="'.$g.'G · '.$e.'E · '.$p.'P">'
                .'<i class="g" style="width:'.round($g * 100 / $total, 1).'%"></i>'
                .'<i class="e" style="width:'.round($e * 100 / $total, 1).'%"></i>'
                .'<i class="p" style="width:'.round($p * 100 / $total, 1).'%"></i></span>';
        };
        // La posición llega como HTML desde el controlador (campeón / subcampeón /
        // número / "Título Extra"). Ojo: 'subcampeon.png' contiene 'campeon.png'.
        $vePosicion = function ($html) {
            $html = (string) $html;
            if (trim(strip_tags($html)) === '' && strpos($html, 'img') === false) { return ''; }
            $sub  = strpos($html, 'subcampeon.png') !== false;
            $camp = !$sub && strpos($html, 'campeon.png') !== false;
            $texto = trim(strip_tags($html));
            return '<span class="t-ficha-pos '.($camp ? 't-campeon' : '').'">'
                .$html.(is_numeric($texto) ? '°' : '').'</span>';
        };
        $veDif = function ($d) {
            $color = $d > 0 ? 'var(--t-win)' : ($d < 0 ? 'var(--t-loss)' : 'inherit');
            return '<span style="color:'.$color.'">'.($d > 0 ? '+' : '').$d.'</span>';
        };
    @endphp

    <div class="container t-ficha-pagina">

        {{-- Cabecera del club --}}
        <div class="t-ficha">
            @if($equipo->escudo)
                <img class="t-ficha-foto t-ficha-escudo" src="{{ url('images/'.$equipo->escudo) }}"
                     alt="{{ $equipo->nombre }}" loading="lazy">
            @else
                <span class="t-ficha-foto t-ficha-escudo escudo escudo-txt">{{ $equipo->siglas }}</span>
            @endif

            <div class="t-ficha-cuerpo">
                <span class="t-eyebrow">{{ $equipo->siglas ?: 'Club' }}</span>

                <h1 class="t-ficha-nombre">
                    {{ $equipo->nombre }}
                    @if($equipo->pais)
                        <img class="bandera" src="{{ $equipo->bandera_url }}"
                             alt="{{ $equipo->pais }}" title="{{ $equipo->pais }}">
                    @endif
                </h1>

                @php
                    $veDatos = [
                        'País'      => $equipo->pais,
                        'Fundación' => $veFundado ? $veFundado->format('d/m/Y').' · '.$veFundado->age.' años' : '',
                        'Estadio'   => $equipo->estadio,
                        'Socios'    => $equipo->socios ? number_format($equipo->socios, 0, ',', '.') : '',
                    ];
                    $veDatos = array_filter($veDatos, function ($v) { return trim((string) $v) !== ''; });
                @endphp
                @if(count($veDatos))
                    <div class="t-ficha-chips">
                        @foreach($veDatos as $veEtiqueta => $veValor)
                            <span class="t-dato"><span>{{ $veEtiqueta }}</span><b>{{ $veValor }}</b></span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Números de toda la historia registrada --}}
        <div class="t-kpis">
            <div class="t-kpi">
                <div class="t-kpi-num">{{ $veJugados }}</div>
                <div class="t-kpi-rot">Partidos</div>
            </div>
            <div class="t-kpi t-kpi-acento">
                <div class="t-kpi-num">{{ $veEfec }}<small>%</small></div>
                <div class="t-kpi-rot">Efectividad</div>
            </div>
            <div class="t-kpi t-kpi-win">
                <div class="t-kpi-num">{{ $veGanados }}</div>
                <div class="t-kpi-rot">Ganados</div>
            </div>
            <div class="t-kpi">
                <div class="t-kpi-num">{{ $veEmpatados }}</div>
                <div class="t-kpi-rot">Empatados</div>
            </div>
            <div class="t-kpi t-kpi-loss">
                <div class="t-kpi-num">{{ $vePerdidos }}</div>
                <div class="t-kpi-rot">Perdidos</div>
            </div>
            <div class="t-kpi">
                <div class="t-kpi-num">{{ count($torneosEquipo) }}</div>
                <div class="t-kpi-rot">Torneos</div>
            </div>
            <div class="t-kpi {{ $veTitulos > 0 ? 't-kpi-win' : 't-kpi-apagado' }}">
                <div class="t-kpi-num">{{ $veTitulos }}</div>
                <div class="t-kpi-rot">Títulos</div>
            </div>
        </div>

        <div class="t-kpis-pie">
            <span>Goles <b>{{ $veFavor }}</b> a favor · <b>{{ $veContra }}</b> en contra
                ({!! $veDif($veFavor - $veContra) !!})</span>
            <span class="t-referencia">{!! $veBarra($veGanados, $veEmpatados, $vePerdidos) !!} balance histórico</span>
        </div>

        {{-- Pestañas --}}
        <ul class="nav nav-tabs" id="equipoTabs" role="tablist">
            @php
                $vePestanas = [
                    'historia'  => 'Historia',
                    'titulos'   => 'Títulos',
                    'tabla'     => 'Torneos',
                    'partidos'  => 'Partidos',
                    'jugadores' => 'Jugadores',
                ];
            @endphp
            @foreach($vePestanas as $veClave => $veNombre)
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $vePest == $veClave ? 'active' : '' }}"
                       id="{{ $veClave }}-tab" data-bs-toggle="tab"
                       href="#{{ $veClave }}" role="tab">{{ $veNombre }}</a>
                </li>
            @endforeach
        </ul>

        <div class="tab-content" id="equipoTabsContent">

            {{-- Historia --}}
            <div class="tab-pane fade {{ $vePest == 'historia' ? 'show active' : '' }}" id="historia" role="tabpanel">
                @if(trim((string) $equipo->historia) !== '')
                    <p class="t-prosa">{!! nl2br(e($equipo->historia)) !!}</p>
                @else
                    <div class="t-vacio"><i class="bi bi-journal-text"></i>Todavía no hay historia cargada para este club.</div>
                @endif
            </div>

            {{-- Títulos --}}
            <div class="tab-pane fade {{ $vePest == 'titulos' ? 'show active' : '' }}" id="titulos" role="tabpanel">
                <div class="t-kpis">
                    <div class="t-kpi {{ $veTitulos > 0 ? 't-kpi-win' : 't-kpi-apagado' }}">
                        <div class="t-kpi-num">{{ $veTitulos }}</div>
                        <div class="t-kpi-rot">Total</div>
                    </div>
                    <div class="t-kpi">
                        <div class="t-kpi-num">{{ $titulosLiga }}</div>
                        <div class="t-kpi-rot">Ligas nacionales</div>
                    </div>
                    <div class="t-kpi">
                        <div class="t-kpi-num">{{ $titulosCopa }}</div>
                        <div class="t-kpi-rot">Copas nacionales</div>
                    </div>
                    <div class="t-kpi">
                        <div class="t-kpi-num">{{ $titulosInternacional }}</div>
                        <div class="t-kpi-rot">Internacionales</div>
                    </div>
                </div>

                @if(count($torneosTitulos) === 0)
                    <div class="t-vacio"><i class="bi bi-trophy"></i>Todavía no hay títulos cargados.</div>
                @else
                    <div class="t-tabla-wrap">
                        <table class="t-tabla">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Torneo</th>
                                <th title="Puntos">Pts</th>
                                <th title="Jugados">J</th>
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
                            @foreach($torneosTitulos as $veIndice => $veTit)
                                <tr>
                                    <td class="t-pos">{{ $veIndice + 1 }}</td>
                                    <td>
                                        <span class="t-torneo">
                                            <x-escudo :src="$veTit->escudoTorneo ?? null" :nombre="$veTit->nombreTorneo" tam="sm"/>
                                            {{ $veTit->nombreTorneo }}
                                        </span>
                                    </td>
                                    <td class="t-pts">{{ $veTit->puntaje }}</td>
                                    <td>{{ $veTit->jugados }}</td>
                                    <td>{!! $veCero($veTit->ganados) !!}</td>
                                    <td>{!! $veCero($veTit->empatados) !!}</td>
                                    <td>{!! $veCero($veTit->perdidos) !!}</td>
                                    <td>{{ $veTit->favor }}</td>
                                    <td>{{ $veTit->contra }}</td>
                                    <td>{!! $veDif($veTit->favor - $veTit->contra) !!}</td>
                                    <td>
                                        {{ $veTit->porcentaje }}<br>
                                        {!! $veBarra($veTit->ganados, $veTit->empatados, $veTit->perdidos) !!}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Torneos --}}
            <div class="tab-pane fade {{ $vePest == 'tabla' ? 'show active' : '' }}" id="tabla" role="tabpanel">
                @if(count($torneosEquipo) === 0)
                    <div class="t-vacio"><i class="bi bi-calendar-x"></i>Este club todavía no tiene torneos cargados.</div>
                @else
                    <div class="t-tabla-wrap">
                        <table class="t-tabla">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Torneo</th>
                                <th class="t-izq">Posición</th>
                                <th title="Puntos">Pts</th>
                                <th title="Jugados">J</th>
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
                            @foreach($torneosEquipo as $veIndice => $veT)
                                <tr>
                                    <td class="t-pos">{{ $veIndice + 1 }}</td>
                                    <td>
                                        <span class="t-torneo">
                                            <x-escudo :src="$veT->escudoTorneo ?? null" :nombre="$veT->nombreTorneo" tam="sm"/>
                                            {{ $veT->nombreTorneo }}
                                        </span>
                                    </td>
                                    <td class="t-izq">{!! $vePosicion($veT->posicion) !!}</td>
                                    <td class="t-pts">{{ $veT->puntaje }}</td>
                                    <td><a href="{{ route('equipos.jugados', ['equipoId' => $equipo->id, 'torneoId' => $veT->idTorneo]) }}">{{ $veT->jugados }}</a></td>
                                    <td><a href="{{ route('equipos.jugados', ['equipoId' => $equipo->id, 'torneoId' => $veT->idTorneo, 'tipo' => 'Ganados']) }}">{!! $veCero($veT->ganados) !!}</a></td>
                                    <td><a href="{{ route('equipos.jugados', ['equipoId' => $equipo->id, 'torneoId' => $veT->idTorneo, 'tipo' => 'Empatados']) }}">{!! $veCero($veT->empatados) !!}</a></td>
                                    <td><a href="{{ route('equipos.jugados', ['equipoId' => $equipo->id, 'torneoId' => $veT->idTorneo, 'tipo' => 'Perdidos']) }}">{!! $veCero($veT->perdidos) !!}</a></td>
                                    <td>{{ $veT->favor }}</td>
                                    <td>{{ $veT->contra }}</td>
                                    <td>{!! $veDif($veT->favor - $veT->contra) !!}</td>
                                    <td>
                                        {{ $veT->porcentaje }}<br>
                                        {!! $veBarra($veT->ganados, $veT->empatados, $veT->perdidos) !!}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                            <tfoot>
                            <tr class="t-totales">
                                <td></td>
                                <td>Totales</td>
                                <td class="t-izq">{{ count($torneosEquipo) }} torneos</td>
                                <td class="t-pts">{{ $vePuntaje }}</td>
                                <td><a href="{{ route('equipos.jugados', ['equipoId' => $equipo->id]) }}">{{ $veJugados }}</a></td>
                                <td><a href="{{ route('equipos.jugados', ['equipoId' => $equipo->id, 'tipo' => 'Ganados']) }}">{{ $veGanados }}</a></td>
                                <td><a href="{{ route('equipos.jugados', ['equipoId' => $equipo->id, 'tipo' => 'Empatados']) }}">{{ $veEmpatados }}</a></td>
                                <td><a href="{{ route('equipos.jugados', ['equipoId' => $equipo->id, 'tipo' => 'Perdidos']) }}">{{ $vePerdidos }}</a></td>
                                <td>{{ $veFavor }}</td>
                                <td>{{ $veContra }}</td>
                                <td>{!! $veDif($veFavor - $veContra) !!}</td>
                                <td>{{ $veEfec }}%</td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Partidos --}}
            <div class="tab-pane fade {{ $vePest == 'partidos' ? 'show active' : '' }}" id="partidos" role="tabpanel">
                @if($partidos->total() === 0)
                    <div class="t-vacio"><i class="bi bi-calendar-x"></i>No hay partidos cargados.</div>
                @else
                    <div class="t-panel t-lista-partidos">
                        @foreach($partidos as $vePartido)
                            <x-partido :p="$vePartido" :torneo="true" :destacar="$equipo->id"/>
                        @endforeach
                    </div>
                    <div class="t-pie-lista">
                        {{ $partidos->appends(['pestActiva' => 'partidos'])->links() }}
                        <span>{{ $partidos->total() }} partidos</span>
                    </div>
                @endif
            </div>

            {{-- Jugadores --}}
            <div class="tab-pane fade {{ $vePest == 'jugadores' ? 'show active' : '' }}" id="jugadores" role="tabpanel">
                @php
                    $veColumnas = [
                        'jugados'   => ['J',    'Partidos jugados'],
                        'titulos'   => ['Tít.', 'Títulos'],
                        'Goles'     => ['Goles','Goles'],
                        'amarillas' => ['TA',   'Tarjetas amarillas'],
                        'rojas'     => ['TR',   'Tarjetas rojas'],
                        'errados'   => ['P. Err.',  'Penales errados'],
                        'atajos'    => ['P. Ataj.', 'Penales atajados'],
                        'recibidos' => ['GC',   'Goles recibidos (arquero)'],
                        'invictas'  => ['VI',   'Vallas invictas (arquero)'],
                    ];
                @endphp

                @if($jugadores->total() === 0)
                    <div class="t-vacio"><i class="bi bi-people"></i>No hay jugadores cargados para este club.</div>
                @else
                    <div class="t-tabla-wrap">
                        <table class="t-tabla">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Jugador</th>
                                @foreach($veColumnas as $veClave => $veCol)
                                    <th title="{{ $veCol[1] }}" class="{{ $order == $veClave ? 't-orden-activo' : '' }}">
                                        <a href="{{ route('equipos.ver', [
                                            'equipoId'   => $equipo->id,
                                            'pestActiva' => 'jugadores',
                                            'order'      => $veClave,
                                            'tipoOrder'  => ($order == $veClave && $tipoOrder == 'ASC') ? 'DESC' : 'ASC',
                                        ]) }}">
                                            {{ $veCol[0] }}
                                            @if($order == $veClave)
                                                <i class="bi {{ $tipoOrder == 'ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                                            @endif
                                        </a>
                                    </th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($jugadores as $veJ)
                                <tr>
                                    <td class="t-pos">{{ $iterator++ }}</td>
                                    <td>
                                        <span class="t-persona-celda">
                                            <a href="{{ route('jugadores.ver', ['jugadorId' => $veJ->jugador_id]) }}">
                                                <img src="{{ url('images/'.($veJ->foto ?: 'sin_foto.png')) }}"
                                                     alt="{{ $veJ->jugador }}" loading="lazy">
                                            </a>
                                            <a href="{{ route('jugadores.ver', ['jugadorId' => $veJ->jugador_id]) }}">{{ $veJ->jugador }}</a>
                                        </span>
                                    </td>
                                    <td class="t-pts"><a href="{{ route('jugadores.jugados', ['jugadorId' => $veJ->jugador_id]) }}">{{ $veJ->jugados }}</a></td>
                                    <td><a href="{{ route('jugadores.titulos', ['jugadorId' => $veJ->jugador_id]) }}">{!! $veCero($veJ->titulos) !!}</a></td>
                                    <td><a href="{{ route('jugadores.goles', ['jugadorId' => $veJ->jugador_id]) }}">{!! $veCero($veJ->goles) !!}</a></td>
                                    <td><a href="{{ route('jugadores.tarjetas', ['jugadorId' => $veJ->jugador_id, 'tipo' => 'Amarilla']) }}">{!! $veCero($veJ->amarillas) !!}</a></td>
                                    <td><a href="{{ route('jugadores.tarjetas', ['jugadorId' => $veJ->jugador_id, 'tipo' => 'Roja']) }}">{!! $veCero($veJ->rojas) !!}</a></td>
                                    <td><a href="{{ route('jugadores.penals', ['jugadorId' => $veJ->jugador_id]) }}">{!! $veCero($veJ->errados) !!}</a></td>
                                    <td><a href="{{ route('jugadores.penals', ['jugadorId' => $veJ->jugador_id, 'tipo' => 'Atajo']) }}">{!! $veCero($veJ->atajos) !!}</a></td>
                                    <td>
                                        {!! $veCero($veJ->recibidos) !!}
                                        @if($veJ->jugados > 0 && $veJ->recibidos > 0)
                                            <span class="t-prom">({{ number_format($veJ->recibidos / $veJ->jugados, 2) }})</span>
                                        @endif
                                    </td>
                                    <td>{!! $veCero($veJ->invictas) !!}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="t-pie-lista">
                        {{ $jugadores->appends(['pestActiva' => 'jugadores', 'order' => $order, 'tipoOrder' => $tipoOrder])->links() }}
                        <span>{{ $jugadores->total() }} jugadores</span>
                    </div>
                @endif
            </div>

        </div>

        <div class="d-flex justify-content-start my-4">
            <a href="{{ url()->previous() }}" class="btn btn-success btn-sm">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>

    </div>
@endsection
