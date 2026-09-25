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
        // Club desaparecido: la columna puede no estar todavía (se agrega por SQL
        // después del deploy), por eso se lee con getAttribute y no rompe.
        $veDesap = $equipo->getAttribute('desaparicion');
        $veDesap = ($veDesap && substr($veDesap, 0, 4) !== '0000') ? \Carbon\Carbon::parse($veDesap) : null;

        $veCero = function ($v) {
            return $v > 0 ? e($v) : '<span class="t-cero">0</span>';
        };
        // Barra proporcional G/E/P.
        $veBarra = function ($g, $e, $p) {
            $total = $g + $e + $p;
            if ($total <= 0) { return ''; }
            return '<span class="t-ge" title="'.$g.__('G').' · '.$e.__('E').' · '.$p.__('P').'">'
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
                <span class="t-eyebrow">{{ $equipo->siglas ?: __('Club') }}</span>

                <h1 class="t-ficha-nombre">
                    {{ $equipo->nombre }}
                    @if($equipo->pais)
                        <img class="bandera" src="{{ $equipo->bandera_url }}"
                             alt="{{ trad_dato($equipo->pais) }}" title="{{ trad_dato($equipo->pais) }}">
                    @endif
                </h1>

                @php
                    $veDatos = [
                        'País'      => trad_dato($equipo->pais),
                        // Con el club desaparecido la edad de hoy no dice nada: se muestran
                        // los años que existió, en el renglón de la desaparición.
                        'Fundación' => $veFundado ? $veFundado->format('d/m/Y').($veDesap ? '' : ' · '.trans_choice(':n año|:n años', $veFundado->age, ['n' => $veFundado->age])) : '',
                        'Desaparición' => $veDesap
                            ? $veDesap->format('d/m/Y').($veFundado && $veFundado->lte($veDesap) ? ' · '.trans_choice(':n año de historia|:n años de historia', $veFundado->diffInYears($veDesap), ['n' => $veFundado->diffInYears($veDesap)]) : '')
                            : '',
                        'Estadio'   => $equipo->estadio,
                        'Socios'    => $equipo->socios ? number_format($equipo->socios, 0, ',', '.') : '',
                    ];
                    $veDatos = array_filter($veDatos, function ($v) { return trim((string) $v) !== ''; });
                @endphp
                @if(count($veDatos))
                    <div class="t-ficha-chips">
                        @foreach($veDatos as $veEtiqueta => $veValor)
                            <span class="t-dato"><span>{{ __($veEtiqueta) }}</span><b>{{ $veValor }}</b></span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Números de toda la historia registrada --}}
        <div class="t-kpis">
            <div class="t-kpi">
                <div class="t-kpi-num">{{ $veJugados }}</div>
                <div class="t-kpi-rot">{{ __('Partidos') }}</div>
            </div>
            <div class="t-kpi t-kpi-acento">
                <div class="t-kpi-num">{{ $veEfec }}<small>%</small></div>
                <div class="t-kpi-rot">{{ __('Efectividad') }}</div>
            </div>
            <div class="t-kpi t-kpi-win">
                <div class="t-kpi-num">{{ $veGanados }}</div>
                <div class="t-kpi-rot">{{ __('Ganados') }}</div>
            </div>
            <div class="t-kpi">
                <div class="t-kpi-num">{{ $veEmpatados }}</div>
                <div class="t-kpi-rot">{{ __('Empatados') }}</div>
            </div>
            <div class="t-kpi t-kpi-loss">
                <div class="t-kpi-num">{{ $vePerdidos }}</div>
                <div class="t-kpi-rot">{{ __('Perdidos') }}</div>
            </div>
            <div class="t-kpi">
                <div class="t-kpi-num">{{ count($torneosEquipo) }}</div>
                <div class="t-kpi-rot">{{ __('Torneos') }}</div>
            </div>
            <div class="t-kpi {{ $veTitulos > 0 ? 't-kpi-win' : 't-kpi-apagado' }}">
                <div class="t-kpi-num">{{ $veTitulos }}</div>
                <div class="t-kpi-rot">{{ __('Títulos') }}</div>
            </div>
        </div>

        <div class="t-kpis-pie">
            <span>{!! __('Goles :favor a favor · :contra en contra', ['favor' => '<b>'.e($veFavor).'</b>', 'contra' => '<b>'.e($veContra).'</b>']) !!}
                ({!! $veDif($veFavor - $veContra) !!})</span>
            <span class="t-referencia">{!! $veBarra($veGanados, $veEmpatados, $vePerdidos) !!} {{ __('balance histórico') }}</span>
        </div>

        {{-- Pestañas --}}
        <ul class="nav nav-tabs" id="equipoTabs" role="tablist">
            @php
                $vePestanas = [
                    'historia'  => __('Historia'),
                    'titulos'   => __('Títulos'),
                    'tabla'     => __('Torneos'),
                    'partidos'  => __('Partidos'),
                    'jugadores' => __('Jugadores'),
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
                    <div class="t-vacio"><i class="bi bi-journal-text"></i>{{ __('Todavía no hay historia cargada para este club.') }}</div>
                @endif
            </div>

            {{-- Títulos --}}
            <div class="tab-pane fade {{ $vePest == 'titulos' ? 'show active' : '' }}" id="titulos" role="tabpanel">
                <div class="t-kpis">
                    <div class="t-kpi {{ $veTitulos > 0 ? 't-kpi-win' : 't-kpi-apagado' }}">
                        <div class="t-kpi-num">{{ $veTitulos }}</div>
                        <div class="t-kpi-rot">{{ __('Total') }}</div>
                    </div>
                    <div class="t-kpi">
                        <div class="t-kpi-num">{{ $titulosLiga }}</div>
                        <div class="t-kpi-rot">{{ __('Ligas nacionales') }}</div>
                    </div>
                    <div class="t-kpi">
                        <div class="t-kpi-num">{{ $titulosCopa }}</div>
                        <div class="t-kpi-rot">{{ __('Copas nacionales') }}</div>
                    </div>
                    <div class="t-kpi">
                        <div class="t-kpi-num">{{ $titulosInternacional }}</div>
                        <div class="t-kpi-rot">{{ __('Internacionales') }}</div>
                    </div>
                </div>

                @if(count($torneosTitulos) === 0)
                    <div class="t-vacio"><i class="bi bi-trophy"></i>{{ __('Todavía no hay títulos cargados.') }}</div>
                @else
                    <div class="t-tabla-wrap">
                        <table class="t-tabla">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ __('Torneo') }}</th>
                                <th title="{{ __('Puntos') }}">{{ __('Pts') }}</th>
                                <th title="{{ __('Jugados') }}">{{ __('J') }}</th>
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
                    <div class="t-vacio"><i class="bi bi-calendar-x"></i>{{ __('Este club todavía no tiene torneos cargados.') }}</div>
                @else
                    <div class="t-tabla-wrap">
                        <table class="t-tabla">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ __('Torneo') }}</th>
                                <th class="t-izq">{{ __('Posición') }}</th>
                                <th title="{{ __('Puntos') }}">{{ __('Pts') }}</th>
                                <th title="{{ __('Jugados') }}">{{ __('J') }}</th>
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
                                <td>{{ __('Totales') }}</td>
                                <td class="t-izq">{{ trans_choice(':n torneo|:n torneos', count($torneosEquipo), ['n' => count($torneosEquipo)]) }}</td>
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
                    <div class="t-vacio"><i class="bi bi-calendar-x"></i>{{ __('No hay partidos cargados.') }}</div>
                @else
                    <div class="t-panel t-lista-partidos">
                        @foreach($partidos as $vePartido)
                            <x-partido :p="$vePartido" :torneo="true" :destacar="$equipo->id"/>
                        @endforeach
                    </div>
                    <div class="t-pie-lista">
                        {{ $partidos->appends(['pestActiva' => 'partidos'])->links() }}
                        <span>{{ trans_choice(':n partido|:n partidos', $partidos->total(), ['n' => $partidos->total()]) }}</span>
                    </div>
                @endif
            </div>

            {{-- Jugadores --}}
            <div class="tab-pane fade {{ $vePest == 'jugadores' ? 'show active' : '' }}" id="jugadores" role="tabpanel">
                @php
                    $veColumnas = [
                        'jugados'   => [__('J'),    __('Partidos jugados')],
                        'titulos'   => [__('Tít.'), __('Títulos')],
                        'goles'     => [__('Goles'),__('Goles')],
                        'amarillas' => [__('TA'),   __('Tarjetas amarillas')],
                        'rojas'     => [__('TR'),   __('Tarjetas rojas')],
                        'errados'   => [__('P. Err.'),  __('Penales errados')],
                        'atajos'    => [__('P. Ataj.'), __('Penales atajados')],
                        'recibidos' => [__('GC'),   __('Goles recibidos (arquero)')],
                        'invictas'  => [__('VI'),   __('Vallas invictas (arquero)')],
                    ];
                @endphp

                @if($jugadores->total() === 0)
                    <div class="t-vacio"><i class="bi bi-people"></i>{{ __('No hay jugadores cargados para este club.') }}</div>
                @else
                    <div class="t-tabla-wrap">
                        <table class="t-tabla">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ __('Jugador') }}</th>
                                @foreach($veColumnas as $veClave => $veCol)
                                    <th title="{{ $veCol[1] }}" class="{{ $order == $veClave ? 't-orden-activo' : '' }}">
                                        <a href="{{ route('equipos.ver', [
                                            'equipoId'   => $equipo->id,
                                            'pestActiva' => 'jugadores',
                                            'order'      => $veClave,
                                            'tipoOrder'  => ($order == $veClave && $tipoOrder == 'DESC') ? 'ASC' : 'DESC',
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
                        <span>{{ trans_choice(':n jugador|:n jugadores', $jugadores->total(), ['n' => $jugadores->total()]) }}</span>
                    </div>
                @endif
            </div>

        </div>

        <div class="d-flex justify-content-start my-4">
            <a href="{{ url()->previous() }}" class="btn btn-success btn-sm">
                <i class="bi bi-arrow-left"></i> {{ __('Volver') }}
            </a>
        </div>

    </div>
@endsection
