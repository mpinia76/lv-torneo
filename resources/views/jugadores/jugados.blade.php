@extends('layouts.appPublic')

@section('pageTitle', __('Partidos jugados'))

@section('content')
    <script type="text/javascript" src="{{ asset('js/echarts.min.js') }}"></script>
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="t-titulo">{{ __('Jugados') }}</h1>

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

                        {{-- Foto jugador --}}
                        <div class="mb-3">
                            <img
                                src="{{ $jugador->persona->foto ? url('images/'.$jugador->persona->foto) : url('images/sin_foto.png') }}"
                                alt="{{ __('Foto de :nombre', ['nombre' => $jugador->persona->getFullNameAttribute()]) }}"
                                class="img-fluid rounded shadow-sm"
                                height="200">
                        </div>

                        {{-- Nombre jugador --}}
                        <div>
                            <a href="{{ route('jugadores.ver', ['jugadorId' => $jugador->id]) }}">
                                <strong>{{ $jugador->persona->getFullNameAgeAttribute() }}</strong>
                            </a>
                        </div>

                    </div>

                    <div class="col-xs-12 col-sm-6 col-md-8" id="detalle">
                        <div class="row text-center">

                            {{-- Cards de Jugados/Ganados/Empatados/Perdidos --}}
                            @php
                                $opciones = [
                                        '' => ['label' => __('Jugados'), 'total' => $totalJugados + ($totalManuales ?? 0)],
                                        'Ganados' => ['label' => __('Ganados'), 'total' => $totalGanados],
                                        'Empatados' => ['label' => __('Empatados'), 'total' => $totalEmpatados],
                                        'Perdidos' => ['label' => __('Perdidos'), 'total' => $totalPerdidos],
                                    ];
                            @endphp

                            @foreach($opciones as $tipoClave => $opcion)
                                <div class="col-6 col-md-3 mb-2">
                                    <a href="{{ route('jugadores.jugados', array_filter([
                                        'jugadorId' => $jugador->id,
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

                            @if(($totalManuales ?? 0) > 0)
                                <div class="row mt-2">
                                    <div class="col-12 text-center">
                                        <small class="text-muted">
                                            {{ trans_choice('Incluye :n partido cargado manualmente sin detalle de resultado, no representado en el gráfico ni en la tabla.|Incluye :n partidos cargados manualmente sin detalle de resultado, no representados en el gráfico ni en la tabla.', (int) $totalManuales, ['n' => $totalManuales]) }}
                                        </small>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>

                {{-- Tabla de partidos --}}
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="t-panel t-lista-partidos">
                        @foreach($partidos as $partido)
                            <x-partido :p="$partido"/>
                        @endforeach
                    </div>

                        {{-- Paginación y total --}}
                        <div class="row">
                            <div class="col-md-9">
                                {{ $partidos->links() }}
                            </div>
                            <div class="col-md-3 text-end">
                                <strong>{{ __('Total: :n', ['n' => $partidos->total()]) }}</strong>
                            </div>
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

    <style>
        tr[onclick]:hover {
            background-color: #d1f7d1 !important;
            transition: background-color 0.2s ease-in-out;
        }
    </style>
@endsection
