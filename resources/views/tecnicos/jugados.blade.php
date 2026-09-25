@extends('layouts.appPublic')

@section('pageTitle', __('Partidos dirigidos'))

@section('content')
    <script type="text/javascript" src="{{ asset('js/echarts.min.js') }}"></script>
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="t-titulo">{{ __('Dirigidos') }}</h1>

                <div class="row">
                    <div class="col-xs-12 col-sm-6 col-md-3">

                        {{-- Torneo --}}
                        @if($torneo)
                            <div class="mb-3 d-flex align-items-center">
                                @if($torneo->escudo)
                                    <img src="{{ url('images/'.$torneo->escudo) }}" alt="{{ __('Escudo :nombre', ['nombre' => $torneo->nombre]) }}" height="40" class="me-2">
                                @endif
                                <strong>{{ $torneo->getFullNameAttribute() }}</strong>
                            </div>
                        @endif

                        {{-- Foto técnico --}}
                        <div class="mb-3">
                            <img
                                src="{{ $tecnico->persona->foto ? url('images/'.$tecnico->persona->foto) : url('images/sin_foto.png') }}"
                                alt="{{ __('Foto de :nombre', ['nombre' => $tecnico->persona->getFullNameAttribute()]) }}"
                                class="img-fluid rounded shadow-sm"
                                height="200">
                        </div>

                        {{-- Nombre técnico --}}
                        <div>
                            <a href="{{ route('tecnicos.ver', ['tecnicoId' => $tecnico->id]) }}">
                                <strong>{{ $tecnico->persona->getFullNameAgeAttribute() }}</strong>
                            </a>
                        </div>

                    </div>

                    <div class="col-xs-12 col-sm-6 col-md-8" id="detalle">
                        <div class="row text-center">

                            {{-- Cards de Jugados/Ganados/Empatados/Perdidos --}}
                            @php
                                $opciones = [
                                    '' => ['label' => __('Jugados'), 'total' => $totalJugados],
                                    'Ganados' => ['label' => __('Ganados'), 'total' => $totalGanados],
                                    'Empatados' => ['label' => __('Empatados'), 'total' => $totalEmpatados],
                                    'Perdidos' => ['label' => __('Perdidos'), 'total' => $totalPerdidos],
                                ];
                            @endphp

                            @php
                                $manualesActivos = [
                                    '' => $totalManualesJugados ?? 0,
                                    'Ganados' => $totalManualesGanados ?? 0,
                                    'Empatados' => $totalManualesEmpatados ?? 0,
                                    'Perdidos' => $totalManualesPerdidos ?? 0,
                                ][$tipo] ?? 0;
                            @endphp
                            @foreach($opciones as $tipoClave => $opcion)
                                <div class="col-6 col-md-3 mb-2">
                                    <a href="{{ route('tecnicos.jugados', array_filter([
                                    'tecnicoId' => $tecnico->id,
                                    'torneoId' => $torneo->id ?? null,
                                    'tipo' => $tipoClave ?: null
                                ])) }}">
                                        <div class="p-2 rounded {{ $tipo == $tipoClave ? 'bg-success text-white' : 'bg-light' }}">
                                            <div>{{ $opcion['label'] }}</div>
                                            <strong>{{ $opcion['total'] }}</strong>
                                        </div>
                                    </a>
                                </div>
                            @endforeach
                        </div>

                        {{-- Gráfico --}}
                        @if($tipo=='')
                            <div class="row mt-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="chart-container">
                                            <div class="chart has-fixed-height" id="pie_basic"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                @if($manualesActivos > 0)
                    <div class="row mt-2">
                        <div class="col-12 text-center">
                            <small class="text-muted">
                                {{ trans_choice(':n partido cargado manualmente no está listado en la tabla.|:n partidos cargados manualmente no están listados en la tabla.', $manualesActivos, ['n' => $manualesActivos]) }}
                            </small>
                        </div>
                    </div>
                @endif
                {{-- Tabla de partidos --}}
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="table-responsive">
                            <div class="t-panel t-lista-partidos">
                        @foreach($partidos as $partido)
                            <x-partido :p="$partido"/>
                        @endforeach
                    </div>
                        </div>

                        {{-- Paginación y total --}}
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            {{ $partidos->links() }}
                            <strong>{{ __('Total: :total', ['total' => $partidos->total()]) }}</strong>
                        </div>
                    </div>
                </div>

                {{-- Botón volver --}}
                <div class="d-flex mt-3">
                    <a href="{{ url()->previous() }}" class="btn btn-success">{{ __('Volver') }}</a>
                </div>

            </div>
        </div>
    </div>

    {{-- Script gráfico --}}
    <script type="text/javascript">
        var pie_basic_element = document.getElementById('pie_basic');
        if (pie_basic_element) {
            var pie_basic = echarts.init(pie_basic_element);
            pie_basic.setOption({
                color: ['#26eb0e','#e5cf0d','#f90a23'],
                legend: {
                    orient: 'horizontal',
                    bottom: 0,
                    left: 'center',
                    data: [@json(__('Ganados')), @json(__('Empatados')), @json(__('Perdidos'))]
                },
                tooltip: {
                    trigger: 'item',
                    formatter: "{b}: {c} ({d}%)"
                },
                series: [{
                    name: @json(__('Partidos')),
                    type: 'pie',
                    radius: '70%',
                    center: ['50%', '50%'],
                    data: [
                        {value: {{ $totalGanados }}, name: @json(__('Ganados'))},
                        {value: {{ $totalEmpatados }}, name: @json(__('Empatados'))},
                        {value: {{ $totalPerdidos }}, name: @json(__('Perdidos'))}
                    ]
                }]
            });
        }
    </script>
@endsection
