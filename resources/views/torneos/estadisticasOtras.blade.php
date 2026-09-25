@extends('layouts.appPublic')

@section('pageTitle', __('Estadísticas del Torneo'))

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="t-titulo">{{ __('Estadísticas Generales') }}</h1>
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            @php
                $tabs = [
                    'torneos' => __('Torneos'),
                    'fechas' => __('Fechas'),
                    'partidos' => __('Partidos'),
                     'resumen' => __('Resumen General'),
                     'graficos' => __('Gráficos'),
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
                        <h5>{{ __($title) }}</h5>
                    @endif
                    <x-estadisticas-tab :data="$estadisticas[$key]" :columns="[
                        __('Torneo') => 'nombreTorneo',

                        __('Goles') => 'goles',
                        __('Promedio') => 'promedio',
                        __('Partidos') => 'partidos',
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
                        <h5>{{ __($title) }}</h5>
                    @endif
                    <x-estadisticas-tab :data="$estadisticas[$key]" :columns="[
                        __('Torneo') => 'nombreTorneo',

                        __('Fecha') => 'numero',
                        __('Goles') => 'goles',
                        __('Promedio') => 'promedio',
                        __('Partidos') => 'partidos',
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
                        <h5>{{ __($title) }}</h5>
                    @endif
                    <x-estadisticas-partidos :data="$estadisticas[$key]" :showTorneo="true"/>
                @endforeach
            </div>

            {{-- Resumen General --}}
            <div class="tab-pane fade" id="resumen" role="tabpanel" aria-labelledby="resumen-tab">
                <x-estadisticas-tab :data="$estadisticasResumen" :columns="[
                    __('Torneo') => 'nombreTorneo',

                    __('Partidos') => 'partidos',
                    __('Goles') => 'goles',
                    __('Promedio') => 'promedio_goles',
                    __('Max 1 partido') => 'max_goles',
                     __('Goles Local') => 'goles_local',
                    __('Goles Visitante') => 'goles_visitante',
                    __('Goles Neutrales') => 'goles_neutral',
                    __('Amarillas') => 'amarillas',
                    __('Rojas') => 'rojas',

                ]"/>
            </div>

            <div class="tab-pane fade" id="graficos" role="tabpanel" aria-labelledby="graficos-tab">
                <h1 class="t-titulo">{{ __('Estadísticas Generales en Gráficos') }}</h1>

                <div class="row">
                    {{-- 1️⃣ Goles totales por torneo --}}
                    <div class="col-md-6 mb-4">
                        <div class="card shadow p-3">
                            <h5 class="text-center text-primary">{{ __('Goles por Torneo') }}</h5>
                            <canvas id="golesPorTorneo"></canvas>
                        </div>
                    </div>

                    {{-- 2️⃣ Promedio de goles por torneo --}}
                    <div class="col-md-6 mb-4">
                        <div class="card shadow p-3">
                            <h5 class="text-center text-warning">{{ __('Promedio de Goles por Torneo') }}</h5>
                            <canvas id="promedioGolesTorneo"></canvas>
                        </div>
                    </div>

                    {{-- 3️⃣ Distribución goles locales vs visitantes --}}
                    <div class="col-md-12 mb-4">
                        <div class="card shadow p-3">
                            <h5 class="text-center text-danger">{{ __('Goles Locales vs Visitantes') }}</h5>
                            <canvas id="localesVsVisitantes"></canvas>
                        </div>
                    </div>
                </div>
            </div>


        </div>

        <div class="d-flex mt-3">
            <a href="{{ url()->previous() }}" class="btn btn-success">{{ __('Volver') }}</a>
        </div>
            </div>
        </div>
    </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const datosResumen = @json($estadisticasResumen);

            // 1️⃣ Goles por Torneo
            const ctxGoles = document.getElementById('golesPorTorneo').getContext('2d');
            new Chart(ctxGoles, {
                type: 'bar',
                data: {
                    labels: datosResumen.map(d => d.nombreTorneo),
                    datasets: [{
                        label: @json(__('Goles')),
                        data: datosResumen.map(d => d.goles),
                        backgroundColor: 'rgba(54, 162, 235, 0.7)'
                    }]
                },
                options: { responsive: true }
            });

            // 2️⃣ Promedio de goles por Torneo
            const ctxPromedio = document.getElementById('promedioGolesTorneo').getContext('2d');
            new Chart(ctxPromedio, {
                type: 'bar',
                data: {
                    labels: datosResumen.map(d => d.nombreTorneo),
                    datasets: [{
                        label: @json(__('Promedio Goles')),
                        data: datosResumen.map(d => d.promedio_goles),
                        backgroundColor: 'rgba(255, 206, 86, 0.7)'
                    }]
                },
                options: { responsive: true }
            });

            // 3️⃣ Goles Locales vs Visitantes
            const ctxLocalesVisitantes = document.getElementById('localesVsVisitantes').getContext('2d');
            new Chart(ctxLocalesVisitantes, {
                type: 'bar',
                data: {
                    labels: datosResumen.map(d => d.nombreTorneo),
                    datasets: [
                        {
                            label: @json(__('Goles Locales')),
                            data: datosResumen.map(d => d.goles_local),
                            backgroundColor: 'rgba(75, 192, 192, 0.7)'
                        },
                        {
                            label: @json(__('Goles Visitante')),
                            data: datosResumen.map(d => d.goles_visitante),
                            backgroundColor: 'rgba(255, 99, 132, 0.7)'
                        }
                    ]
                },
                options: { responsive: true }
            });
        </script>


@endsection
