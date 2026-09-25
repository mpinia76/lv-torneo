@extends('layouts.appPublic')

@section('pageTitle', __('Penales'))

@section('content')
    <script type="text/javascript" src="{{ asset('js/echarts.min.js') }}"></script>

    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="t-titulo">{{ __('Penales') }}</h1>

                <div class="row">
                    {{-- COLUMNA IZQUIERDA (Jugador y Torneo) --}}
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
                        <div class="mb-3 text-center">
                            <img src="{{ $jugador->persona->foto ? url('images/'.$jugador->persona->foto) : url('images/sin_foto.png') }}"
                                 alt="{{ __('Foto de :nombre', ['nombre' => $jugador->persona->getFullNameAttribute()]) }}"
                                 class="img-fluid rounded shadow-sm"
                                 height="200">
                        </div>

                        {{-- Nombre jugador --}}
                        <div class="text-center">
                            <a href="{{ route('jugadores.ver', ['jugadorId' => $jugador->id]) }}">
                                <strong>{{ $jugador->persona->getFullNameAgeAttribute() }}</strong>
                            </a>
                        </div>
                    </div>

                    {{-- COLUMNA DERECHA (Estadísticas y Gráficos) --}}
                    <div class="col-xs-12 col-sm-6 col-md-8" id="detalle">

                        {{-- Estadísticas del jugador --}}
                        <div class="row text-center">
                            @php
                                $opciones = [
                                    '' => ['label' => __('Todos'), 'valorDB' => ''],
                                    'Convertidos' => ['label' => __('Convertidos'), 'valorDB' => 'Convertido'],
                                    'Errados' => ['label' => __('Errados'), 'valorDB' => 'Errado'],
                                    'Atajados' => ['label' => __('Atajados'), 'valorDB' => 'Atajado'],
                                ];
                            @endphp

                            @foreach($opciones as $tipoClave => $opcion)
                                <div class="col-6 col-md-3 mb-2">
                                    <a href="{{ route('jugadores.penals', array_filter([
                                        'jugadorId' => $jugador->id,
                                        'torneoId' => $torneo->id ?? null,
                                        'tipo' => $opcion['valorDB'] ?: null
                                    ])) }}">
                                        <div class="p-2 rounded {{ $tipo == $opcion['valorDB'] ? 'bg-success text-white' : 'bg-light' }}">
                                            <div>{{ $opcion['label'] }}</div>
                                            <strong>
                                                @switch($tipoClave)
                                                    @case('') {{ $totalTodos }} @break
                                                    @case('Convertidos') {{ $totalConvertidos }} @break
                                                    @case('Errados') {{ $totalErrados }} @break
                                                    @case('Atajados') {{ $totalAtajados }} @break
                                                @endswitch
                                            </strong>
                                        </div>
                                    </a>
                                </div>
                            @endforeach
                        </div>

                        {{-- Gráfico de penales del jugador --}}
                        @if($tipo == '')
                            <div class="card shadow-sm mt-3">
                                <div class="card-body">
                                    <div id="pie_basic" style="height:300px;"></div>
                                </div>
                            </div>
                        @endif

                        <h5 class="card-title text-center mt-4">🧤 {{ __('Penales al arquero') }}</h5>
                        <div class="row text-center">
                            @php
                                $opciones = [
                                    '' => ['label' => __('Todos'), 'valorDB' => ''],
                                    'Convirtieron' => ['label' => __('Convirtieron'), 'valorDB' => 'Convirtieron'],
                                    'Atajó' => ['label' => __('Atajó'), 'valorDB' => 'Atajó'],
                                ];
                            @endphp

                            @foreach($opciones as $tipoClave => $opcion)
                                <div class="col-6 col-md-3 mb-2">
                                    <a href="{{ route('jugadores.penals', array_filter([
                                        'jugadorId' => $jugador->id,
                                        'torneoId' => $torneo->id ?? null,
                                        'tipo' => $opcion['valorDB'] ?: null
                                    ])) }}">
                                        <div class="p-2 rounded {{ $tipo == $opcion['valorDB'] ? 'bg-success text-white' : 'bg-light' }}">
                                            <div>{{ $opcion['label'] }}</div>
                                            <strong>
                                                @switch($tipoClave)
                                                    @case('') {{ $totalTodosArquero }} @break
                                                    @case('Convirtieron') {{ $totalConvirtieron }} @break
                                                    @case('Atajó') {{ $totalAtajos }} @break
                                                @endswitch
                                            </strong>
                                        </div>
                                    </a>
                                </div>
                            @endforeach
                        </div>

                        {{-- Gráfico de penales al arquero --}}
                        @if($tipo == '' && $totalTodosArquero > 0)
                            <div class="card shadow-sm mt-3">
                                <div class="card-body">
                                    <div id="pie_arqueros" style="height:300px;"></div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Nota de penales manuales --}}
                @if($penalesManuales > 0)
                    <div class="alert alert-info small mt-3 mb-3">
                        ℹ️ {!! trans_choice('Se incluye <strong>:n</strong> penal cargado manualmente en los totales.|Se incluyen <strong>:n</strong> penales cargados manualmente en los totales.', (int) $penalesManuales, ['n' => (int) $penalesManuales]) !!} {{ __('Los partidos correspondientes no se listan abajo porque no tienen detalle disponible.') }}
                    </div>
                @endif

                {{-- Tabla de partidos --}}
                <div class="card shadow-sm mt-3 mb-4">
                    <div class="card-body">
                        <div class="t-panel t-lista-partidos">
                            @foreach($partidos as $partido)
                                <x-partido :p="$partido"/>
                            @endforeach
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

    {{-- Gráficos de torta --}}
    <script>
        var pie_basic_element = document.getElementById('pie_basic');
        if (pie_basic_element) {
            var pie_basic = echarts.init(pie_basic_element);
            pie_basic.setOption({
                color: ['#4caf50', '#f44336', '#2196f3'],
                tooltip: { trigger: 'item', formatter: "{b}: {c} ({d}%)" },
                legend: {
                    bottom: '0%',
                    left: 'center',
                    data: [@json(__('Convertidos')), @json(__('Errados')), @json(__('Atajados'))]
                },
                series: [{
                    name: @json(__('Penales')),
                    type: 'pie',
                    radius: '70%',
                    center: ['50%', '50%'],
                    data: [
                        {value: {{ (int) $totalConvertidos }}, name: @json(__('Convertidos'))},
                        {value: {{ (int) $totalErrados }}, name: @json(__('Errados'))},
                        {value: {{ (int) $totalAtajados }}, name: @json(__('Atajados'))}
                    ]
                }]
            });
            window.addEventListener('resize', function () { pie_basic.resize(); });
        }

        var pie_arqueros_element = document.getElementById('pie_arqueros');
        if (pie_arqueros_element) {
            var pie_arqueros = echarts.init(pie_arqueros_element);
            pie_arqueros.setOption({
                color: ['#4caf50', '#f44336'],
                tooltip: { trigger: 'item', formatter: "{b}: {c} ({d}%)" },
                legend: {
                    bottom: '0%',
                    left: 'center',
                    data: [@json(__('Atajó')), @json(__('Convirtieron'))]
                },
                series: [{
                    name: @json(__('Penales al arquero')),
                    type: 'pie',
                    radius: '70%',
                    center: ['50%', '50%'],
                    data: [
                        {value: {{ (int) $totalAtajos }}, name: @json(__('Atajó'))},
                        {value: {{ (int) $totalConvirtieron }}, name: @json(__('Convirtieron'))}
                    ]
                }]
            });
            window.addEventListener('resize', function () { pie_arqueros.resize(); });
        }
    </script>
@endsection
