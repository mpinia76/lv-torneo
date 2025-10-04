@extends('layouts.appPublic')

@section('pageTitle', 'Tarjetas')

@section('content')
    <script type="text/javascript" src="{{ asset('js/echarts.min.js') }}"></script>
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="h3 mb-4 text-center text-primary">🟨🟥 Tarjetas</h1>

                <div class="row">
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
                        <div class="mb-3">
                            <img
                                src="{{ $jugador->persona->foto ? url('images/'.$jugador->persona->foto) : url('images/sin_foto.png') }}"
                                alt="Foto de {{ $jugador->persona->getFullNameAttribute() }}"
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

                            {{-- Cards de tarjetas --}}
                            @php
                                $opciones = [
                                    '' => ['label' => 'Todas', 'valorDB' => ''],
                                    'Rojas' => ['label' => 'Rojas', 'valorDB' => 'Roja'],
                                    'Amarillas' => ['label' => 'Amarillas', 'valorDB' => 'Amarilla'],
                                ];
                            @endphp

                            @foreach($opciones as $tipoClave => $opcion)
                                <div class="col-6 col-md-3 mb-2">
                                    <a href="{{ route('jugadores.tarjetas', array_filter([
                                                'jugadorId' => $jugador->id,
                                                'torneoId' => $torneo->id ?? null,
                                                'tipo' => $opcion['valorDB'] ?: null
                                            ])) }}">
                                        <div class="p-2 rounded {{ $tipo == $opcion['valorDB'] ? 'bg-success text-white' : 'bg-light' }}">
                                            <div>{{ $opcion['label'] }}</div>
                                            <strong>
                                                @if($tipoClave == '')
                                                    {{ $totalTodos }}
                                                @elseif($tipoClave == 'Rojas')
                                                    {{ $totalRojas }}
                                                @else
                                                    {{ $totalAmarillas }}
                                                @endif
                                            </strong>
                                        </div>
                                    </a>
                                </div>
                            @endforeach
                        </div>

                        {{-- Gráfico --}}
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
                    </div>
                </div>

                {{-- Tabla de partidos --}}
                <div class="row mt-4">
                    <div class="col-md-12">
                        <div class="table-responsive">
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
                                    <tr style="cursor:pointer;" onclick="window.location='{{ route('fechas.detalle', ['partidoId' => $partido->partido_id]) }}'">
                                        <td>
                                            @if($partido->escudoTorneo)
                                                <img src="{{ url('images/'.$partido->escudoTorneo) }}" alt="Escudo {{ $partido->nombreTorneo }}" height="20" class="me-1">
                                            @endif
                                            {{ $partido->nombreTorneo }} {{ $partido->year }}
                                        </td>
                                        <td>{{ is_numeric($partido->numero) ? 'Fecha '.$partido->numero : $partido->numero }}</td>
                                        <td>{{ $partido->dia ? date('d/m/Y H:i', strtotime($partido->dia)) : '' }}</td>
                                        <td>
                                            <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipol_id]) }}">
                                                @if($partido->fotoLocal)
                                                    <img src="{{ url('images/'.$partido->fotoLocal) }}" height="20" class="me-1">
                                                @endif
                                                {{ $partido->local }}
                                            </a>
                                        </td>
                                        <td>{{ $partido->golesl }} @if(isset($partido->penalesl)) ({{ $partido->penalesl }}) @endif</td>
                                        <td>{{ $partido->golesv }} @if(isset($partido->penalesv)) ({{ $partido->penalesv }}) @endif</td>
                                        <td>
                                            <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipov_id]) }}">
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
                        </div>

                        {{-- Paginación y total --}}
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            {{ $partidos->links() }}
                            <strong>Total: {{ $partidos->total() }}</strong>
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

    {{-- Script gráfico --}}
    <script type="text/javascript">
        var pie_basic_element = document.getElementById('pie_basic');
        if (pie_basic_element) {
            var pie_basic = echarts.init(pie_basic_element);
            pie_basic.setOption({
                color: ['#f90a23','#e5cf0d'],
                legend: {
                    orient: 'horizontal',
                    bottom: 0,
                    left: 'center',
                    data: ['Rojas', 'Amarillas']
                },
                tooltip: {
                    trigger: 'item',
                    formatter: "{b}: {c} ({d}%)"
                },
                series: [{
                    name: 'Tarjetas',
                    type: 'pie',
                    radius: '70%',
                    center: ['50%', '50%'],
                    data: [
                        {value: {{ $totalRojas }}, name: 'Rojas'},
                        {value: {{ $totalAmarillas }}, name: 'Amarillas'}
                    ]
                }]
            });
        }
    </script>
@endsection
