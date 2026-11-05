@extends('layouts.appPublic')

@php
    // Totales de la carrera (también los usa la descripción para buscadores).
    $vaT          = collect($torneosArbitro);
    $vaPartidos   = (int) $vaT->sum('partidos');
    $vaPrincipal  = (int) $vaT->sum('principal');
    $vaLinea      = (int) $vaT->sum('linea');
    $vaCuarto     = (int) $vaT->sum('cuarto');
    $vaVar        = (int) $vaT->sum('var');
    $vaConDetalle = (int) $vaT->sum('con_detalle');
    $vaAmarillas  = (int) $vaT->sum('amarillas');
    $vaRojas      = (int) $vaT->sum('rojas');
    $vaDirectas   = (int) $vaT->sum('directas');
    $vaDobles     = (int) $vaT->sum('dobles');
    $vaPenales    = (int) $vaT->sum('penales');

    $seoNombre = $arbitro->persona->name ?: __('Árbitro');
    $seoDesc = $vaPartidos > 0
        ? $seoNombre . ': ' . cantidad($vaPartidos, 'partido', 'partidos')
            . ($vaPrincipal > 0 ? ' (' . __(':n como principal', ['n' => $vaPrincipal]) . ')' : '')
            . ' ' . __('en') . ' ' . cantidad($vaT->count(), 'torneo', 'torneos') . '. '
            . __('Todos sus partidos y las tarjetas que mostró.')
        : __(':nombre: ficha de árbitro.', ['nombre' => $seoNombre]);
@endphp
@section('pageTitle', __(':nombre — partidos dirigidos y tarjetas', ['nombre' => $seoNombre]))
@section('pageDescription', $seoDesc)
@php datos_estructurados(persona_ld($arbitro->persona, url_canonica(), __('Árbitro de fútbol'))); @endphp
@if($arbitro->persona->foto)
    @section('pageImage', url_imagen($arbitro->persona->foto))
@endif
{{-- Sin partidos la ficha queda vacía: fuera de los buscadores. --}}
@if($vaPartidos === 0)
    @section('robots', 'noindex, follow')
@endif

@section('content')
    @php
        $vaP = $arbitro->persona;
        $vaDatos = [
            __('Nacido') => $vaP->nacimiento ? trim($vaP->getAgeAttribute()) : '',
            __('Ciudad') => $vaP->ciudad,
        ];

        $vaCero = function ($v) {
            return $v > 0 ? e($v) : '<span class="t-cero">0</span>';
        };
        // Tarjetas por partido, solo sobre partidos con el detalle cargado.
        $vaProm = function ($tarjetas, $partidos) {
            return $partidos > 0
                ? number_format($tarjetas / $partidos, 2, app()->getLocale() === 'en' ? '.' : ',', '')
                : '–';
        };
        $vaRolNombre = [
            'Principal' => __('Principal'),
            'Linea 1'   => __('Asistente'),
            'Linea 2'   => __('Asistente'),
            'Cuarto'    => __('Cuarto árbitro'),
            'VAR'       => __('VAR'),
        ];
        // Chips del filtro por rol: [rótulo, cantidad]. Con un torneo elegido,
        // las cantidades son las de ese torneo.
        $vaBase = $torneoFiltro ?: (object) [
            'partidos' => $vaPartidos, 'principal' => $vaPrincipal, 'linea' => $vaLinea,
            'cuarto' => $vaCuarto, 'var' => $vaVar,
        ];
        $vaFiltroRol = [
            ''          => [__('Todos'), (int) $vaBase->partidos],
            'principal' => [__('Principal'), (int) $vaBase->principal],
            'linea'     => [__('Asistente'), (int) $vaBase->linea],
            'cuarto'    => [__('Cuarto árbitro'), (int) $vaBase->cuarto],
            'var'       => [__('VAR'), (int) $vaBase->var],
        ];
        // Barra G / E / P de cada equipo (igual que en la ficha del técnico).
        $vaGep = function ($g, $e, $p) {
            $total = $g + $e + $p;
            if ($total <= 0) { return ''; }
            return '<span class="t-ge" title="'.$g.__('G').' · '.$e.__('E').' · '.$p.__('P').'">'
                .'<i class="g" style="width:'.round($g * 100 / $total, 1).'%"></i>'
                .'<i class="e" style="width:'.round($e * 100 / $total, 1).'%"></i>'
                .'<i class="p" style="width:'.round($p * 100 / $total, 1).'%"></i></span>';
        };
        $vaPorc = function ($n, $total) {
            return $total > 0 ? round($n * 100 / $total) . '%' : '–';
        };
        $vaRes = $resultados ?? null;
        $vaUrl = function (array $cambios) use ($arbitro, $idTorneo, $rol) {
            $q = array_merge(['arbitroId' => $arbitro->id, 'torneoId' => $idTorneo ?: null, 'rol' => $rol ?: null], $cambios);
            return route('arbitros.ver', array_filter($q, function ($v) { return $v !== null && $v !== ''; })) . '#partidos';
        };
    @endphp

    <div class="container t-ficha-pagina">

        <x-ficha-persona :persona="$vaP" :rol="__('Árbitro')" fallback="sin_foto_arbitro.png" :datos="$vaDatos"/>

        @if($vaPartidos === 0)
            <div class="t-panel">
                <div class="t-vacio">
                    <i class="bi bi-clipboard-x"></i>
                    {{ __('Todavía no hay estadísticas de arbitraje cargadas para esta ficha.') }}
                </div>
            </div>
        @else

            <div class="t-kpis">
                <div class="t-kpi">
                    <div class="t-kpi-num">{{ $vaPartidos }}</div>
                    <div class="t-kpi-rot">{{ __('Partidos') }}</div>
                </div>
                <div class="t-kpi t-kpi-acento">
                    <div class="t-kpi-num">{{ $vaPrincipal }}</div>
                    <div class="t-kpi-rot">{{ __('Como principal') }}</div>
                </div>
                <div class="t-kpi {{ $vaLinea > 0 ? '' : 't-kpi-apagado' }}">
                    <div class="t-kpi-num">{{ $vaLinea }}</div>
                    <div class="t-kpi-rot">{{ __('Como asistente') }}</div>
                </div>
                @if($vaCuarto + $vaVar > 0)
                    <div class="t-kpi">
                        <div class="t-kpi-num">{{ $vaCuarto + $vaVar }}</div>
                        <div class="t-kpi-rot">{{ __('Cuarto / VAR') }}</div>
                    </div>
                @endif
                <div class="t-kpi">
                    <div class="t-kpi-num">{{ $vaT->count() }}</div>
                    <div class="t-kpi-rot">{{ __('Torneos') }}</div>
                </div>
                <div class="t-kpi">
                    <div class="t-kpi-num t-num-amarilla">{{ $vaAmarillas }}</div>
                    <div class="t-kpi-rot">{{ __('Amarillas') }}</div>
                </div>
                <div class="t-kpi">
                    <div class="t-kpi-num t-num-roja">{{ $vaRojas }}</div>
                    <div class="t-kpi-rot">{{ __('Rojas') }}</div>
                </div>
                <div class="t-kpi {{ $vaPenales > 0 ? '' : 't-kpi-apagado' }}">
                    <div class="t-kpi-num">{{ $vaPenales }}</div>
                    <div class="t-kpi-rot">{{ __('Penales') }}</div>
                </div>
            </div>

            <div class="t-kpis-pie">
                @if($vaConDetalle > 0)
                    <span>{!! __('Por partido: :amarillas amarillas · :rojas rojas', [
                        'amarillas' => '<b>'.e($vaProm($vaAmarillas, $vaConDetalle)).'</b>',
                        'rojas'     => '<b>'.e($vaProm($vaRojas, $vaConDetalle)).'</b>',
                    ]) !!} · {!! __(':penales penales', [
                        'penales' => '<b>'.e($vaProm($vaPenales, $vaConDetalle)).'</b>',
                    ]) !!}</span>
                @endif
                @if($vaRojas > 0)
                    <span>{!! __('Rojas: :directas directas · :dobles por doble amarilla', [
                        'directas' => '<b>'.e($vaDirectas).'</b>',
                        'dobles'   => '<b>'.e($vaDobles).'</b>',
                    ]) !!}</span>
                @endif
                @if($vaRes && (int) $vaRes->total > 0)
                    <span>{!! __('Con él: gana el local :local · empate :empate · gana el visitante :visitante', [
                        'local'     => '<b>'.e($vaPorc((int) $vaRes->local, (int) $vaRes->total)).'</b>',
                        'empate'    => '<b>'.e($vaPorc((int) $vaRes->empate, (int) $vaRes->total)).'</b>',
                        'visitante' => '<b>'.e($vaPorc((int) $vaRes->visitante, (int) $vaRes->total)).'</b>',
                    ]) !!}</span>
                @endif
                <span class="t-referencia">{{ __('Tarjetas de los partidos en que fue el árbitro principal; la doble amarilla cuenta como roja.') }}</span>
            </div>

            <div class="t-panel">
                <div class="t-tabla-wrap">
                    <table class="t-tabla">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ __('Torneo') }}</th>
                            <th title="{{ __('Partidos') }}">{{ __('PJ') }}</th>
                            <th title="{{ __('Como principal') }}">{{ __('Princ.') }}</th>
                            <th title="{{ __('Como asistente') }}">{{ __('Asist.') }}</th>
                            <th title="{{ __('Cuarto árbitro') }}">{{ __('4.º') }}</th>
                            <th title="{{ __('VAR') }}">{{ __('VAR') }}</th>
                            <th title="{{ __('Amarillas') }}"><span class="t-tarjeta-a"></span></th>
                            <th title="{{ __('Rojas') }}"><span class="t-tarjeta-r"></span></th>
                            <th title="{{ __('Amarillas por partido como principal') }}">{{ __('Prom.') }}</th>
                            <th title="{{ __('Penales cobrados como principal') }}">{{ __('Pen.') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($torneosArbitro as $vaIndice => $vaFila)
                            <tr>
                                <td class="t-pos">{{ $vaIndice + 1 }}</td>
                                <td>
                                    <a class="t-torneo" href="{{ route('torneos.ver', ['torneoId' => $vaFila->idTorneo]) }}">
                                        <x-escudo :src="$vaFila->escudoTorneo" :nombre="$vaFila->nombreTorneo" tam="sm"/>
                                        {{ $vaFila->nombreTorneo }}
                                    </a>
                                </td>
                                <td><a href="{{ $vaUrl(['torneoId' => $vaFila->idTorneo, 'rol' => null]) }}">{{ $vaFila->partidos }}</a></td>
                                <td>{!! $vaCero($vaFila->principal) !!}</td>
                                <td>{!! $vaCero($vaFila->linea) !!}</td>
                                <td>{!! $vaCero($vaFila->cuarto) !!}</td>
                                <td>{!! $vaCero($vaFila->var) !!}</td>
                                <td>{!! $vaCero($vaFila->amarillas) !!}</td>
                                <td>{!! $vaCero($vaFila->rojas) !!}</td>
                                <td>{{ $vaProm($vaFila->amarillas, $vaFila->con_detalle) }}</td>
                                <td>{!! $vaCero($vaFila->penales) !!}</td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr class="t-totales">
                            <td></td>
                            <td>{{ __('Totales') }}</td>
                            <td><a href="{{ $vaUrl(['torneoId' => null, 'rol' => null]) }}">{{ $vaPartidos }}</a></td>
                            <td>{{ $vaPrincipal }}</td>
                            <td>{{ $vaLinea }}</td>
                            <td>{{ $vaCuarto }}</td>
                            <td>{{ $vaVar }}</td>
                            <td>{{ $vaAmarillas }}</td>
                            <td>{{ $vaRojas }}</td>
                            <td>{{ $vaProm($vaAmarillas, $vaConDetalle) }}</td>
                            <td>{{ $vaPenales }}</td>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            {{-- Equipos que dirigió como principal: cómo les fue con él y cuántas tarjetas les mostró --}}
            @if(!empty($porEquipo))
                <h2 class="t-seccion" id="equipos">{{ __('Por equipo') }}</h2>
                <div class="t-panel">
                    <div class="t-tabla-wrap">
                        <table class="t-tabla">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th class="t-izq">{{ __('Equipo') }}</th>
                                <th title="{{ __('Partidos dirigidos como principal') }}">{{ __('PJ') }}</th>
                                <th title="{{ __('Ganados') }}">{{ __('G') }}</th>
                                <th title="{{ __('Empatados') }}">{{ __('E') }}</th>
                                <th title="{{ __('Perdidos') }}">{{ __('P') }}</th>
                                <th></th>
                                <th title="{{ __('Goles a favor') }}">{{ __('GF') }}</th>
                                <th title="{{ __('Goles en contra') }}">{{ __('GC') }}</th>
                                <th title="{{ __('Amarillas que le mostró') }}"><span class="t-tarjeta-a"></span></th>
                                <th title="{{ __('Rojas que le mostró') }}"><span class="t-tarjeta-r"></span></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($porEquipo as $vaEqIndice => $vaEq)
                                <tr>
                                    <td class="t-pos">{{ $vaEqIndice + 1 }}</td>
                                    <td class="t-izq">
                                        <x-celda-equipo :href="route('equipos.ver', ['equipoId' => $vaEq->equipo_id])"
                                                        :nombre="$vaEq->nombre" :escudo="$vaEq->escudo" :pais="$vaEq->pais" tam="sm"/>
                                    </td>
                                    <td class="t-pts">{{ $vaEq->pj }}</td>
                                    <td>{!! $vaCero($vaEq->g) !!}</td>
                                    <td>{!! $vaCero($vaEq->e) !!}</td>
                                    <td>{!! $vaCero($vaEq->p) !!}</td>
                                    <td>{!! $vaGep((int) $vaEq->g, (int) $vaEq->e, (int) $vaEq->p) !!}</td>
                                    <td>{{ $vaEq->gf }}</td>
                                    <td>{{ $vaEq->gc }}</td>
                                    <td>{!! $vaCero($vaEq->amarillas) !!}</td>
                                    <td>{!! $vaCero($vaEq->rojas) !!}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Partidos, filtrables por torneo (desde la tabla) y por rol --}}
            <h2 class="t-seccion" id="partidos">
                {{ __('Partidos') }}
                @if($torneoFiltro)
                    <small class="text-muted">· {{ $torneoFiltro->nombreTorneo }}</small>
                @endif
            </h2>

            <div class="t-lista-filtros">
                @foreach($vaFiltroRol as $vaClave => $vaChip)
                    @if($vaClave === '' || $vaChip[1] > 0)
                        <a class="t-chip {{ $rol === $vaClave ? 't-chip-acento' : '' }}"
                           href="{{ $vaUrl(['rol' => $vaClave ?: null]) }}">
                            {{ $vaChip[0] }} <b>{{ $vaChip[1] }}</b>
                        </a>
                    @endif
                @endforeach
                @if($torneoFiltro)
                    <a class="t-chip" href="{{ $vaUrl(['torneoId' => null]) }}" title="{{ __('Ver todos los torneos') }}">
                        {{ $torneoFiltro->nombreTorneo }} <i class="bi bi-x-lg"></i>
                    </a>
                @endif
            </div>

            <div class="t-panel t-lista-partidos">
                @forelse($partidos as $vaPartido)
                    @php $vaTar = $tarjetasPartido[(int) $vaPartido->partido_id] ?? null; @endphp
                    <x-partido :p="$vaPartido">
                        @if($vaTar && ($vaTar->amarillas || $vaTar->rojas))
                            <span class="t-chip" title="{{ __('Tarjetas') }}">
                                @if($vaTar->amarillas)<span class="t-tarjeta-a"></span> {{ $vaTar->amarillas }}@endif
                                @if($vaTar->rojas) <span class="t-tarjeta-r"></span> {{ $vaTar->rojas }}@endif
                            </span>
                        @endif
                        @if($vaPartido->rol !== 'Principal')
                            <span class="t-chip">{{ $vaRolNombre[$vaPartido->rol] ?? $vaPartido->rol }}</span>
                        @endif
                    </x-partido>
                @empty
                    <div class="t-vacio"><i class="bi bi-clipboard-x"></i>{{ __('No hay partidos con este filtro.') }}</div>
                @endforelse
                @if($partidos->hasPages())
                    <div class="t-panel-pie t-paginacion">
                        {{ $partidos->links() }}
                        <span class="ms-auto">{{ __('Total: :total', ['total' => $partidos->total()]) }}</span>
                    </div>
                @endif
            </div>
        @endif

        <div class="d-flex justify-content-start my-4">
            <a href="{{ url_volver() }}" class="btn btn-success btn-sm">
                <i class="bi bi-arrow-left"></i> {{ __('Volver') }}
            </a>
        </div>
    </div>
@endsection
