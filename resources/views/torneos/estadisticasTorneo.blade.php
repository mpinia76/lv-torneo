@extends('layouts.appPublic')

@section('pageTitle', 'Otras estadísticas')

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
        <h1 class="h3 mb-4 text-center text-warning">🏆 Estadísticas por Torneo</h1>
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            @php
                $tabs = [
                    'goles' => 'Goles',
                    'fechaMasGol' => 'Fechas con más goles',
                    'fechaMenosGol' => 'Fechas con menos goles',
                    'partidoMasGol' => 'Partidos con más goles',
                    'partidoMenosGol' => 'Partidos con menos goles',
                    'promedioEquipo' => 'Promedio por equipo',
                ];
            @endphp
            @foreach($tabs as $id => $label)
                <li class="nav-item" role="presentation">
                    <button class="nav-link @if($loop->first) active @endif"
                            id="{{ $id }}-tab"
                            data-bs-toggle="tab"
                            data-bs-target="#{{ $id }}"
                            type="button"
                            role="tab"
                            aria-controls="{{ $id }}"
                            aria-selected="@if($loop->first) true @else false @endif">
                        {{ $label }}
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="tab-content mt-3" id="myTabContent">
            {{-- Goles --}}
            <div class="tab-pane fade show active" id="goles" role="tabpanel" aria-labelledby="goles-tab">
                <x-estadisticas-tab :data="$estadisticas['goles']" :columns="[
                'Partidos' => 'total_partidos',
                'Total' => 'total_goles',
                'Promedio' => 'promedio_total',
                'Locales' => 'goles_local',
                'Promedio' => 'promedio_local',
                'Visitante' => 'goles_visitante',
                'Promedio' => 'promedio_visitante',
                'Neutrales' => 'goles_neutral',
                'Promedio' => 'promedio_neutral',
            ]"/>
            </div>

            {{-- Fechas con más goles --}}
            <div class="tab-pane fade" id="fechaMasGol" role="tabpanel" aria-labelledby="fechaMasGol-tab">
                @foreach(['' => 'fechaMasGoles', 'Locales' => 'fechaMasGolesLocales', 'Visitantes' => 'fechaMasGolesVisitantes', 'Neutrales' => 'fechaMasGolesNeutrales'] as $title => $key)
                    @if($title)
                        <h5>{{ $title }}</h5>
                    @endif
                    <x-estadisticas-tab :data="$estadisticas[$key]" :columns="[
                    '#' => 'index',
                    'Fecha' => 'numero',
                    'Goles' => 'goles',
                    'Promedio' => 'promedio',
                    'Partidos' => 'partidos',
                ]"/>
                @endforeach
            </div>

            {{-- Fechas con menos goles --}}
            <div class="tab-pane fade" id="fechaMenosGol" role="tabpanel" aria-labelledby="fechaMenosGol-tab">
                @foreach(['' => 'fechaMinGoles', 'Locales' => 'fechaMinGolesLocales', 'Visitantes' => 'fechaMinGolesVisitantes', 'Neutrales' => 'fechaMinGolesNeutrales'] as $title => $key)
                    @if($title)
                        <h5>{{ $title }}</h5>
                    @endif
                    <x-estadisticas-tab :data="$estadisticas[$key]" :columns="[
                    '#' => 'index',
                    'Fecha' => 'numero',
                    'Goles' => 'goles',
                    'Promedio' => 'promedio',
                    'Partidos' => 'partidos',
                ]"/>
                @endforeach
            </div>

            {{-- Partidos con más goles --}}
            <div class="tab-pane fade" id="partidoMasGol" role="tabpanel" aria-labelledby="partidoMasGol-tab">
                @foreach(['' => 'maxGoles', 'Locales' => 'maxGolesLocales', 'Visitantes' => 'maxGolesVisitantes', 'Neutrales' => 'maxGolesNeutrales'] as $title => $key)
                    @if($title)
                        <h5>{{ $title }}</h5>
                    @endif
                    <x-estadisticas-partidos :data="$estadisticas[$key]"/>
                @endforeach
            </div>

            {{-- Partidos con menos goles --}}
            <div class="tab-pane fade" id="partidoMenosGol" role="tabpanel" aria-labelledby="partidoMenosGol-tab">
                @foreach(['' => 'minGoles', 'Locales' => 'minGolesLocales', 'Visitantes' => 'minGolesVisitantes', 'Neutrales' => 'minGolesNeutrales'] as $title => $key)
                    @if($title)
                        <h5>{{ $title }}</h5>
                    @endif
                    <x-estadisticas-partidos :data="$estadisticas[$key]"/>
                @endforeach
            </div>

            {{-- Promedio por equipo --}}
            <div class="tab-pane fade" id="promedioEquipo" role="tabpanel" aria-labelledby="promedioEquipo-tab">
                <x-estadisticas-tab :data="$estadisticas['promedioEquipo']" :columns="[
        'Equipo' => 'nombre',
        'GF' => 'goles_favor',
        'GC' => 'goles_contra',
        'Dif' => 'diferencia',
        'Prom. GF' => 'promedio_favor',
        'Prom. GC' => 'promedio_contra',
    ]"/>
            </div>

        </div>

        <div class="d-flex mt-3">
            <a href="{{ url()->previous() }}" class="btn btn-success">Volver</a>
        </div>
            </div>
        </div>
    </div>
@endsection
