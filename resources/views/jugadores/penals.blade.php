@extends('layouts.appPublic')

@section('pageTitle', 'Penales')

@section('content')
    <script type="text/javascript" src="{{ asset('js/echarts.min.js') }}"></script>

    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="h3 mb-4 text-center text-success">⚽🥅 Penales</h1>

                <div class="row">
                    {{-- COLUMNA IZQUIERDA (Jugador y Torneo) --}}
                    <div class="col-xs-12 col-sm-6 col-md-3">
                        {{-- Torneo --}}
                        @if($torneo)
                            <div class="mb-3 d-flex align-items-center">
                                @if($torneo->escudo)
                                    <img src="{{ url('images/'.$torneo->escudo) }}" alt="Escudo {{ $torneo->nombre }}" height="40" class="me-2">
                                @endif
                                <strong>{{ $torneo->getFullNameAttribute() }}</strong>
                            </div>
                        @endif

                        {{-- Foto jugador --}}
                        <div class="mb-3 text-center">
                            <img src="{{ $jugador->persona->foto ? url('images/'.$jugador->persona->foto) : url('images/sin_foto.png') }}"
                                 alt="Foto de {{ $jugador->persona->getFullNameAttribute() }}"
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
                                    '' => ['label' => 'Todos', 'valorDB' => ''],
                                    'Convertidos' => ['label' => 'Convertidos', 'valorDB' => 'Convertido'],
                                    'Errados' => ['label' => 'Errados', 'valorDB' => 'Errado'],
                                    'Atajados' => ['label' => 'Atajados', 'valorDB' => 'Atajado'],
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
                        <h5 class="card-title text-center">🧤 Penales al arquero</h5>
                        <div class="row text-center">
                            @php
                                $opciones = [
                                    '' => ['label' => 'Todos', 'valorDB' => ''],
                                    'Convirtieron' => ['label' => 'Convirtieron', 'valorDB' => 'Convirtieron'],
                                    'Atajó' => ['label' => 'Atajó', 'valorDB' => 'Atajó'],
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
