@extends('layouts.appPublic')

@section('pageTitle', __('Otras estadísticas'))

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
        <h1 class="t-titulo">{{ __('Estadísticas por Torneo') }}</h1>
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            @php
                $tabs = [
                    'goles' => __('Goles'),
                    'fechaMasGol' => __('Fechas con más goles'),
                    'fechaMenosGol' => __('Fechas con menos goles'),
                    'partidoMasGol' => __('Partidos con más goles'),
                    'partidoMenosGol' => __('Partidos con menos goles'),
                    'promedioEquipo' => __('Promedio por equipo'),
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
            {{-- Goles --}}
            <div class="tab-pane fade show active" id="goles" role="tabpanel" aria-labelledby="goles-tab">
                <x-estadisticas-tab :data="$estadisticas['goles']" :columns="[
                __('Partidos') => 'total_partidos',
                __('Total') => 'total_goles',
                __('Promedio') => 'promedio_total',
                __('Locales') => 'goles_local',
                __('Prom. Local') => 'promedio_local',
                __('Visitante') => 'goles_visitante',
                __('Prom. Visitante') => 'promedio_visitante',
                __('Neutrales') => 'goles_neutral',
                __('Prom. Neutral') => 'promedio_neutral',
            ]"/>
            </div>

            {{-- Fechas con más goles --}}
            <div class="tab-pane fade" id="fechaMasGol" role="tabpanel" aria-labelledby="fechaMasGol-tab">
                @foreach(['' => 'fechaMasGoles', 'Locales' => 'fechaMasGolesLocales', 'Visitantes' => 'fechaMasGolesVisitantes', 'Neutrales' => 'fechaMasGolesNeutrales'] as $title => $key)
                    @if($title)
                        <h5>{{ __($title) }}</h5>
                    @endif
                    <x-estadisticas-tab :data="$estadisticas[$key]" :columns="[
                    '#' => 'index',
                    __('Fecha') => 'numero',
                    __('Goles') => 'goles',
                    __('Promedio') => 'promedio',
                    __('Partidos') => 'partidos',
                ]"/>
                @endforeach
            </div>

            {{-- Fechas con menos goles --}}
            <div class="tab-pane fade" id="fechaMenosGol" role="tabpanel" aria-labelledby="fechaMenosGol-tab">
                @foreach(['' => 'fechaMinGoles', 'Locales' => 'fechaMinGolesLocales', 'Visitantes' => 'fechaMinGolesVisitantes', 'Neutrales' => 'fechaMinGolesNeutrales'] as $title => $key)
                    @if($title)
                        <h5>{{ __($title) }}</h5>
                    @endif
                    <x-estadisticas-tab :data="$estadisticas[$key]" :columns="[
                    '#' => 'index',
                    __('Fecha') => 'numero',
                    __('Goles') => 'goles',
                    __('Promedio') => 'promedio',
                    __('Partidos') => 'partidos',
                ]"/>
                @endforeach
            </div>

            {{-- Partidos con más goles --}}
            <div class="tab-pane fade" id="partidoMasGol" role="tabpanel" aria-labelledby="partidoMasGol-tab">
                @foreach(['' => 'maxGoles', 'Locales' => 'maxGolesLocales', 'Visitantes' => 'maxGolesVisitantes', 'Neutrales' => 'maxGolesNeutrales'] as $title => $key)
                    @if($title)
                        <h5>{{ __($title) }}</h5>
                    @endif
                    <x-estadisticas-partidos :data="$estadisticas[$key]"/>
                @endforeach
            </div>

            {{-- Partidos con menos goles --}}
            <div class="tab-pane fade" id="partidoMenosGol" role="tabpanel" aria-labelledby="partidoMenosGol-tab">
                @foreach(['' => 'minGoles', 'Locales' => 'minGolesLocales', 'Visitantes' => 'minGolesVisitantes', 'Neutrales' => 'minGolesNeutrales'] as $title => $key)
                    @if($title)
                        <h5>{{ __($title) }}</h5>
                    @endif
                    <x-estadisticas-partidos :data="$estadisticas[$key]"/>
                @endforeach
            </div>

            {{-- Promedio por equipo --}}
            <div class="tab-pane fade" id="promedioEquipo" role="tabpanel" aria-labelledby="promedioEquipo-tab">
                <x-estadisticas-tab :data="$estadisticas['promedioEquipo']" :columns="[
        '#' => 'index',
        __('Equipo') => 'nombre',
        __('GF') => 'goles_favor',
        __('GC') => 'goles_contra',
        __('Dif') => 'diferencia',
        __('Prom. GF') => 'promedio_favor',
        __('Prom. GC') => 'promedio_contra',
    ]"/>
            </div>
            {{-- Gráficos --}}
            <div class="tab-pane fade" id="graficos" role="tabpanel" aria-labelledby="graficos-tab">
                <h1 class="t-titulo">{{ __('Estadísticas en Gráficos') }}</h1>

                <div class="row">
                    {{-- 1. Goles por Equipo --}}
                    <div class="col-md-6 mb-4">
                        <div class="card shadow p-3">
                            <h5 class="text-center text-primary">{{ __('Goles por Equipo') }}</h5>
                            <canvas id="golesPorEquipo"></canvas>
                        </div>
                    </div>

                    {{-- 2. Promedio de Goles (Favor vs Contra) --}}
                    <div class="col-md-6 mb-4">
                        <div class="card shadow p-3">
                            <h5 class="text-center text-warning">{{ __('Promedio de Goles (Favor vs Contra)') }}</h5>
                            <canvas id="promediosGoles"></canvas>
                        </div>
                    </div>

                    {{-- 3. Distribución de Goles (Locales vs Visitantes) --}}
                    <div class="col-md-12 mb-4">
                        <div class="card shadow p-3 d-flex justify-content-center align-items-center" style="height:400px;">
                            <h5 class="text-center text-danger">{{ __('Distribución Locales vs Visitantes') }}</h5>
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
        // --- 1. Goles por Equipo ---
        new Chart(document.getElementById('golesPorEquipo'), {
            type: 'bar',
            data: {
                labels: @json(collect($estadisticas['promedioEquipo'])->pluck('nombre')),
                datasets: [{
                    label: @json(__('Goles a Favor')),
                    data: @json(collect($estadisticas['promedioEquipo'])->pluck('goles_favor')),
                    backgroundColor: 'rgba(54, 162, 235, 0.7)',
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } } }
        });

        // --- 2. Promedio Goles Favor vs Contra ---
        new Chart(document.getElementById('promediosGoles'), {
            type: 'bar',
            data: {
                labels: @json(collect($estadisticas['promedioEquipo'])->pluck('nombre')),
                datasets: [
                    {
                        label: @json(__('Prom. GF')),
                        data: @json(collect($estadisticas['promedioEquipo'])->pluck('promedio_favor')),
                        backgroundColor: 'rgba(75, 192, 192, 0.7)',
                    },
                    {
                        label: @json(__('Prom. GC')),
                        data: @json(collect($estadisticas['promedioEquipo'])->pluck('promedio_contra')),
                        backgroundColor: 'rgba(255, 99, 132, 0.7)',
                    }
                ]
            },
            options: { responsive: true, plugins: { legend: { position: 'top' } } }
        });

        // --- 3. Locales vs Visitantes ---
        new Chart(document.getElementById('localesVsVisitantes'), {
            type: 'pie',
            data: {
                labels: [@json(__('Goles Local')), @json(__('Goles Visitante')), @json(__('Goles Neutrales'))],
                datasets: [{
                    data: [
                        {{ collect($estadisticas['goles'])->pluck('goles_local')->sum() }},
                        {{ collect($estadisticas['goles'])->pluck('goles_visitante')->sum() }},
                        {{ collect($estadisticas['goles'])->pluck('goles_neutral')->sum() }},
                    ],
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(153, 102, 255, 0.7)',
                    ]
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'top' } } }
        });
    </script>

@endsection
