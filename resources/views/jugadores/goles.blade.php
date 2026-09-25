@extends('layouts.appPublic')

@section('pageTitle', __('Goles'))

@section('content')
    <script src="{{ asset('js/echarts.min.js') }}"></script>

    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="t-titulo">{{ __('Goles') }}</h1>

                {{-- Info del torneo y jugador --}}
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-body text-center">
                                @if($torneo)
                                    <h5 class="mb-3">
                                        <strong>{{ $torneo->getFullNameAttribute() }}</strong>
                                    </h5>
                                @endif

                                <div class="mb-3">
                                    <img src="{{ $jugador->persona->foto ? url('images/'.$jugador->persona->foto) : url('images/sin_foto.png') }}"
                                         class="img-fluid rounded shadow-sm"
                                         style="max-height: 200px;">
                                </div>

                                <h5>
                                    <a href="{{ route('jugadores.ver', ['jugadorId' => $jugador->id]) }}">
                                        <strong>{{ $jugador->persona->getFullNameAgeAttribute() }}</strong>
                                    </a>
                                </h5>
                            </div>
                        </div>
                    </div>

                    {{-- Filtros de goles --}}
                    <div class="col-md-8">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <div class="row g-2 text-center">
                                    @php
                                        $tipos = [
                                            '' => ['label' => __('Todos'), 'count' => $totalTodos],
                                            'Jugada' => ['label' => __('Jugada'), 'count' => $totalJugada],
                                            'Cabeza' => ['label' => __('Cabeza'), 'count' => $totalCabeza],
                                            'Penal' => ['label' => __('Penal'), 'count' => $totalPenal],
                                            'Tiro Libre' => ['label' => __('Tiro Libre'), 'count' => $totalTiroLibre],
                                            'Olímpico' => ['label' => __('Olímpico'), 'count' => $totalOlimpico],
                                        ];
                                    @endphp

                                    @foreach($tipos as $tipoKey => $info)
                                        <div class="col-6 col-md-2 mb-2">
                                            @if($torneo)
                                                <a href="{{ route('jugadores.goles', ['jugadorId' => $jugador->id, 'torneoId' => $torneo->id, 'tipo' => $tipoKey]) }}"
                                                   class="btn btn-sm w-100 {{ $tipo === $tipoKey ? 'btn-success' : 'btn-outline-success' }}">
                                                    <div>{{ $info['label'] }}</div>
                                                    <div><strong>{{ $info['count'] }}</strong></div>
                                                </a>
                                            @else
                                                <a href="{{ route('jugadores.goles', ['jugadorId' => $jugador->id, 'tipo' => $tipoKey]) }}"
                                                   class="btn btn-sm w-100 {{ $tipo === $tipoKey ? 'btn-success' : 'btn-outline-success' }}">
                                                    <div>{{ $info['label'] }}</div>
                                                    <div><strong>{{ $info['count'] }}</strong></div>
                                                </a>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- Gráfico circular solo si no hay filtro --}}
                        @if($tipo == '')
                            <div class="card shadow-sm mt-3">
                                <div class="card-body">
                                    <div id="pie_basic" style="height:300px;"></div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Nota de goles manuales --}}
                @if($golesManuales > 0)
                    <div class="alert alert-info small mb-3">
                        ℹ️ {!! trans_choice('Se incluye <strong>:n</strong> gol cargado manualmente en los totales.|Se incluyen <strong>:n</strong> goles cargados manualmente en los totales.', (int) $golesManuales, ['n' => (int) $golesManuales]) !!} {{ __('Los partidos correspondientes no se listan abajo porque no tienen detalle disponible.') }}
                    </div>
                @endif

                {{-- Tabla de partidos --}}
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="table-responsive">
                            <div class="t-panel t-lista-partidos">
                        @foreach($partidos as $partido)
                            <x-partido :p="$partido"/>
                        @endforeach
                    </div>
                        </div>

                        {{-- Paginación --}}
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            {{ $partidos->links() }}
                            <strong>{{ __('Total: :total', ['total' => $partidos->total()]) }}</strong>
                        </div>
                    </div>
                </div>

                <a href="{{ url()->previous() }}" class="btn btn-success">{{ __('Volver') }}</a>
            </div>
        </div>
    </div>

    {{-- Gráfico de torta --}}
    <script>
        var pie_basic_element = document.getElementById('pie_basic');
        if (pie_basic_element) {
            var pie_basic = echarts.init(pie_basic_element);
            pie_basic.setOption({
                color: ['#26eb0e','#e5cf0d','#f90a23','#ffb980','#3aa0ff'],
                tooltip: {
                    trigger: 'item',
                    formatter: "{b}: {c} ({d}%)"
                },
                legend: {
                    bottom: '0%',
                    left: 'center',
                    data: [@json(__('Jugada')),@json(__('Cabeza')),@json(__('Penal')),@json(__('Tiro Libre')),@json(__('Olímpico'))]
                },
                series: [{
                    name: @json(__('Goles')),
                    type: 'pie',
                    radius: '70%',
                    center: ['50%','50%'],
                    data: [
                        {value: {{ $totalJugada }}, name: @json(__('Jugada'))},
                        {value: {{ $totalCabeza }}, name: @json(__('Cabeza'))},
                        {value: {{ $totalPenal }}, name: @json(__('Penal'))},
                        {value: {{ $totalTiroLibre }}, name: @json(__('Tiro Libre'))},
                        {value: {{ $totalOlimpico }}, name: @json(__('Olímpico'))}
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
