@extends('layouts.appPublic')

@php
    // Título y descripción para los buscadores: «Superliga 2019/2020 — tabla de posiciones».
    $seoTorneo = $torneo->nombre . ' ' . $torneo->year;
    $seoTitulo = __(':torneo — tabla de posiciones', ['torneo' => $seoTorneo]);
    $seoDesc   = __(':torneo: tabla de posiciones completa, con partidos jugados, ganados, empatados y perdidos, goles y diferencia.', ['torneo' => $seoTorneo]);
@endphp
@section('pageTitle', $seoTitulo)
@section('pageDescription', $seoDesc)
@if($torneo->escudo)
    @section('pageImage', url_imagen($torneo->escudo))
@endif

@section('content')

    <div class="t-cabecera">
        <div>
            <span class="t-eyebrow">
                <x-escudo :src="$torneo->escudo" :nombre="$torneo->nombre" tam="sm"/>
                {{ $torneo->nombre }} {{ $torneo->year }}
            </span>
            <h1>{{ __('Tabla de posiciones') }}</h1>
        </div>
    </div>

    @foreach($arrPosiciones as $nombre => $data)
        @php
            $i = 1;
            $posiciones = $data['equipos'];
            $numClasificados = $data['clasificados'] ?? 0;
        @endphp

        <div class="t-panel">
            @if(count($arrPosiciones) > 1)
                <div class="t-grupo">
                    <span class="t-grupo-nombre">{{ __('Grupo :nombre', ['nombre' => $nombre]) }}</span>
                </div>
            @endif

            <div class="t-tabla-wrap">
                <table class="t-tabla">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('Equipo') }}</th>
                        <th>{{ __('Pts') }}</th>
                        <th>{{ __('PJ') }}</th>
                        <th>{{ __('G') }}</th>
                        <th>{{ __('E') }}</th>
                        <th>{{ __('P') }}</th>
                        <th>{{ __('GF') }}</th>
                        <th>{{ __('GC') }}</th>
                        <th>{{ __('Dif') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($posiciones as $equipo)
                        @php
                            if (!empty($leyendaZonas)) {
                                $claseFila = $equipo->zonaClase ?? '';
                            } else {
                                $claseFila = $i <= $numClasificados ? 't-clasifica' : '';
                            }
                        @endphp
                        <tr class="{{ $claseFila }}" @if(!empty($leyendaZonas) && !empty($equipo->zona)) title="{{ trad_dato($equipo->zona) }}" @endif>
                            <td class="t-pos">{{ $i }}</td>
                            <td>
                                <span class="t-nombre">
                                    <x-escudo :src="$equipo->foto" :nombre="$equipo->equipo"/>
                                    <a href="{{ route('equipos.ver', ['equipoId' => $equipo->equipo_id]) }}">{{ $equipo->equipo }}</a>
                                    @if($equipo->pais)
                                        <img class="bandera" src="{{ url('images/'.removeAccents($equipo->pais).'.gif') }}" alt="{{ trad_dato($equipo->pais) }}" title="{{ trad_dato($equipo->pais) }}">
                                    @endif
                                </span>
                            </td>
                            <td class="t-pts">{{ $equipo->puntaje }}</td>
                            <td>{{ $equipo->jugados }}</td>
                            <td>{{ $equipo->ganados }}</td>
                            <td>{{ $equipo->empatados }}</td>
                            <td>{{ $equipo->perdidos }}</td>
                            <td>{{ $equipo->golesl }}</td>
                            <td>{{ $equipo->golesv }}</td>
                            <td>{{ $equipo->diferencia > 0 ? '+' . $equipo->diferencia : $equipo->diferencia }}</td>
                        </tr>
                        @php $i++; @endphp
                    @endforeach
                    </tbody>
                </table>
            </div>

            @if(!empty($leyendaZonas))
                <div class="t-panel-pie">
                    @foreach($leyendaZonas as $zonaNombre => $zonaClase)
                        <span class="t-referencia {{ $zonaClase }}"><i></i> {{ trad_dato($zonaNombre) }}</span>
                    @endforeach
                </div>
            @elseif($numClasificados)
                <div class="t-panel-pie">
                    <span class="t-referencia"><i style="background: var(--t-win)"></i> {{ __('Clasifica') }}</span>
                </div>
            @endif
            @foreach($avisosZonas ?? [] as $avisoZona)
                <div class="t-panel-pie" style="color:#b45309">{{ $avisoZona }}</div>
            @endforeach
        </div>
    @endforeach

    @if($incidencias->isNotEmpty())
        <div class="t-panel">
            <div class="t-grupo"><span class="t-grupo-nombre">{{ __('Incidencias del torneo') }}</span></div>
            <div class="t-panel-cuerpo">
                <ul class="mb-0 ps-3">
                    @foreach($incidencias as $incidencia)
                        <li>{{ texto_idioma($incidencia->observaciones, $incidencia->observaciones_en ?? null) }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="d-flex mt-3">
        <a href="{{ route('torneos.ver', ['torneoId' => $torneo->id]) }}" class="btn btn-outline-secondary btn-sm">{{ __('Volver al torneo') }}</a>
    </div>

@endsection
