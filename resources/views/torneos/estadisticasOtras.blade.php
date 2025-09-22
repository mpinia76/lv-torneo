@extends('layouts.appPublic')

@section('pageTitle', 'Estadísticas del Torneo')

@section('content')
    <div class="container">

        <ul class="nav nav-tabs" id="myTab" role="tablist">
            @php
                $tabs = [
                    'torneos' => 'Torneos',
                    'fechas' => 'Fechas',
                    'partidos' => 'Partidos',
                     'resumen' => 'Resumen General'
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
            {{-- Torneos --}}
            <div class="tab-pane fade show active" id="torneos" role="tabpanel" aria-labelledby="torneos-tab">
                @foreach ([
                    '' => 'torneoMasGoles',
                    'Locales' => 'torneoMasGolesLocales',
                    'Visitantes' => 'torneoMasGolesVisitantes',
                    'Neutrales' => 'torneoMasGolesNeutrales',
                ] as $title => $key)
                    @if($title)
                        <h5>{{ $title }}</h5>
                    @endif
                    <x-estadisticas-tab :data="$estadisticas[$key]" :columns="[
                        'Torneo' => 'nombreTorneo',
                        'Año' => 'year',
                        'Goles' => 'goles',
                        'Promedio' => 'promedio',
                        'Partidos' => 'partidos',
                    ]"/>
                @endforeach
            </div>

            {{-- Fechas --}}
            <div class="tab-pane fade" id="fechas" role="tabpanel" aria-labelledby="fechas-tab">
                @foreach ([
                    '' => 'fechaMasGoles',
                    'Locales' => 'fechaMasGolesLocales',
                    'Visitantes' => 'fechaMasGolesVisitantes',
                    'Neutrales' => 'fechaMasGolesNeutrales',
                ] as $title => $key)
                    @if($title)
                        <h5>{{ $title }}</h5>
                    @endif
                    <x-estadisticas-tab :data="$estadisticas[$key]" :columns="[
                        'Torneo' => 'nombreTorneo',
                        'Año' => 'year',
                        'Fecha' => 'numero',
                        'Goles' => 'goles',
                        'Promedio' => 'promedio',
                        'Partidos' => 'partidos',
                    ]"/>
                @endforeach
            </div>

            {{-- Partidos --}}
            <div class="tab-pane fade" id="partidos" role="tabpanel" aria-labelledby="partidos-tab">
                @foreach ([
                    '' => 'maxGoles',
                    'Locales' => 'maxGolesLocales',
                    'Visitantes' => 'maxGolesVisitantes',
                    'Neutrales' => 'maxGolesNeutrales',
                ] as $title => $key)
                    @if($title)
                        <h5>{{ $title }}</h5>
                    @endif
                    <x-estadisticas-partidos :data="$estadisticas[$key]"/>
                @endforeach
            </div>

            {{-- Resumen General --}}
            <div class="tab-pane fade" id="resumen" role="tabpanel" aria-labelledby="resumen-tab">
                <x-estadisticas-tab :data="$estadisticasResumen" :columns="[
                    'Torneo' => 'nombreTorneo',
                    'Año' => 'year',
                    'Partidos' => 'partidos',
                    'Goles' => 'goles',
                    'Promedio' => 'promedio_goles',
                    'Max Goles' => 'max_goles',
                    'Empates' => 'empates',

                    'Goles Visitante' => 'goles_visitante',
                ]"/>
            </div>

        </div>

        <div class="d-flex mt-3">
            <a href="{{ url()->previous() }}" class="btn btn-success">Volver</a>
        </div>

    </div>
@endsection
