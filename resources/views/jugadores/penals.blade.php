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

                        {{-- Gráfico de penales al arquero --}}
                        <div class="row mt-4">
                            <div class="col-md-8 offset-md-2">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title text-center">🧤 Penales al arquero</h5>

                                        <div class="d-flex justify-content-center gap-3 mb-3 flex-wrap">
                                            <a href="{{ route('jugadores.penals', ['jugadorId' => $jugador->id, 'torneoId' => $torneo->id ?? null, 'tipo' => 'Atajo']) }}">
                                                <div class="p-2 rounded {{ $tipo == 'Atajo' ? 'bg-success text-white' : 'bg-light' }}">
                                                    <div>Atajó</div>
                                                    <strong>{{ $totalAtajos ?? 0 }}</strong>
                                                </div>
                                            </a>

                                            <a href="{{ route('jugadores.penals', ['jugadorId' => $jugador->id, 'torneoId' => $torneo->id ?? null, 'tipo' => 'Convertido']) }}">
                                                <div class="p-2 rounded {{ $tipo == 'Convertido' ? 'bg-danger text-white' : 'bg-light' }}">
                                                    <div>Convertido</div>
                                                    <strong>{{ $totalConvirtieron ?? 0 }}</strong>
                                                </div>
                                            </a>
                                        </div>

                                        <div id="pie_arqueros" style="height: 300px;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- Tabla de partidos --}}
                <div class="row mt-4">
                    <div class="col-md-12">
                        <table class="table table-hover align-middle">
                            <thead class="table-dark">
                            <tr>
                                <th>Torneo</th>
                                <th>Fecha</th>
                                <th>Día</th>
                                <th>Local</th>
                                <th>GL</th>
                                <th>GV</th>
                                <th>Visitante</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($partidos as $partido)
                                <tr onclick="window.location='{{ route('fechas.detalle', ['partidoId' => $partido->partido_id]) }}'" style="cursor:pointer;">
                                    <td>
                                        @if($partido->escudoTorneo)
                                            <img src="{{ url('images/'.$partido->escudoTorneo) }}" alt="Escudo {{ $partido->nombreTorneo }}" height="20" class="me-1">
                                        @endif
                                        {{ $partido->nombreTorneo }} {{ $partido->year }}
                                    </td>
                                    <td>{{ is_numeric($partido->numero) ? 'Fecha '.$partido->numero : $partido->numero }}</td>
                                    <td>{{ $partido->dia ? date('d/m/Y H:i', strtotime($partido->dia)) : '' }}</td>
                                    <td>
                                        <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipol_id]) }}" onclick="event.stopPropagation()">
                                            @if($partido->fotoLocal)
                                                <img src="{{ url('images/'.$partido->fotoLocal) }}" height="20" class="me-1">
                                            @endif
                                            {{ $partido->local }}
                                        </a>
                                    </td>
                                    <td>{{ $partido->golesl }} @if(isset($partido->penalesl)) ({{ $partido->penalesl }}) @endif</td>
                                    <td>{{ $partido->golesv }} @if(isset($partido->penalesv)) ({{ $partido->penalesv }}) @endif</td>
                                    <td>
                                        <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipov_id]) }}" onclick="event.stopPropagation()">
                                            @if($partido->fotoVisitante)
                                                <img src="{{ url('images/'.$partido->fotoVisitante) }}" height="20" class="me-1">
                                            @endif
                                            {{ $partido->visitante }}
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>

                        <div class="row">
                            <div class="col-md-9">{{ $partidos->links() }}</div>
                            <div class="col-md-3 text-end"><strong>Total: {{ $partidos->total() }}</strong></div>
                        </div>
                    </div>
                </div>

                {{-- Botón volver --}}
                <div class="d-flex mt-3">
                    <a href="{{ url()->previous() }}" class="btn btn-success">Volver</a>
                </div>
            </div>
        </div>
    </div>

    {{-- Scripts de gráficos --}}
    <script type="text/javascript">
        // Gráfico general
        var pie_basic_element = document.getElementById('pie_basic');
        if (pie_basic_element) {
            var pie_basic = echarts.init(pie_basic_element);
            pie_basic.setOption({
                color: ['#4caf50', '#f44336', '#2196f3'],
                legend: { orient: 'horizontal', bottom: 0, left: 'center', data: ['Convertidos', 'Errados', 'Atajados'] },
                tooltip: { trigger: 'item', formatter: "{b}: {c} ({d}%)" },
                series: [{
                    name: 'Penales',
                    type: 'pie',
                    radius: '70%',
                    center: ['50%', '50%'],
                    data: [
                        {value: {{ $totalConvertidos }}, name: 'Convertidos'},
                        {value: {{ $totalErrados }}, name: 'Errados'},
                        {value: {{ $totalAtajados }}, name: 'Atajados'}
                    ]
                }]
            });
        }

        // Gráfico del arquero
        var pie_arqueros_element = document.getElementById('pie_arqueros');
        if (pie_arqueros_element) {
            var pie_arqueros = echarts.init(pie_arqueros_element);
            pie_arqueros.setOption({
                color: ['#4caf50', '#f44336'],
                legend: { orient: 'horizontal', bottom: 0, left: 'center', data: ['Atajó', 'Convertido'] },
                tooltip: { trigger: 'item', formatter: "{b}: {c} ({d}%)" },
                series: [{
                    name: 'Penales al arquero',
                    type: 'pie',
                    radius: '70%',
                    center: ['50%', '50%'],
                    data: [
                        {value: {{ $totalAtajos ?? 0 }}, name: 'Atajó'},
                        {value: {{ $totalConvirtieron ?? 0 }}, name: 'Convertido'}
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
