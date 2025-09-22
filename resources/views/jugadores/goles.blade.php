@extends('layouts.appPublic')

@section('pageTitle', 'Goles')

@section('content')
    <script src="{{ asset('js/echarts.min.js') }}"></script>

    <div class="container">

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
                                    '' => ['label' => 'Todos', 'count' => $totalTodos],
                                    'Jugada' => ['label' => 'Jugada', 'count' => $totalJugada],
                                    'Cabeza' => ['label' => 'Cabeza', 'count' => $totalCabeza],
                                    'Penal' => ['label' => 'Penal', 'count' => $totalPenal],
                                    'Tiro Libre' => ['label' => 'Tiro Libre', 'count' => $totalTiroLibre],
                                ];
                            @endphp

                            @foreach($tipos as $tipoKey => $info)
                                <div class="col-6 col-md-2">
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

        {{-- Tabla de partidos --}}
        <div class="card shadow-sm mb-4">
            <div class="card-body">
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
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($partidos as $partido)
                            <tr>
                                <td>{{ $partido->nombreTorneo }} {{ $partido->year }}</td>
                                <td>
                                    @if(is_numeric($partido->numero))
                                        Fecha {{ $partido->numero }}
                                    @else
                                        {{ $partido->numero }}
                                    @endif
                                </td>
                                <td>{{ $partido->dia ? date('d/m/Y H:i', strtotime($partido->dia)) : '' }}</td>
                                <td>
                                    <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipol_id]) }}">
                                        @if($partido->fotoLocal)
                                            <img src="{{ url('images/'.$partido->fotoLocal) }}" height="20" class="me-1">
                                        @endif
                                        {{ $partido->local }}
                                    </a>
                                </td>
                                <td>{{ $partido->golesl }} @isset($partido->penalesl) ({{ $partido->penalesl }}) @endisset</td>
                                <td>{{ $partido->golesv }} @isset($partido->penalesv) ({{ $partido->penalesv }}) @endisset</td>
                                <td>
                                    <a href="{{ route('equipos.ver', ['equipoId' => $partido->equipov_id]) }}">
                                        @if($partido->fotoVisitante)
                                            <img src="{{ url('images/'.$partido->fotoVisitante) }}" height="20" class="me-1">
                                        @endif
                                        {{ $partido->visitante }}
                                    </a>
                                </td>
                                <td>
                                    <a href="{{ route('fechas.detalle', ['partidoId' => $partido->partido_id]) }}"
                                       class="btn btn-success btn-sm">Detalles</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Paginación --}}
                <div class="d-flex justify-content-between align-items-center mt-3">
                    {{ $partidos->links() }}
                    <strong>Total: {{ $partidos->total() }}</strong>
                </div>
            </div>
        </div>

        <a href="{{ url()->previous() }}" class="btn btn-success">Volver</a>
    </div>

    {{-- Gráfico de torta --}}
    <script>
        var pie_basic_element = document.getElementById('pie_basic');
        if (pie_basic_element) {
            var pie_basic = echarts.init(pie_basic_element);
            pie_basic.setOption({
                color: ['#26eb0e','#e5cf0d','#f90a23','#ffb980'],
                tooltip: {
                    trigger: 'item',
                    formatter: "{b}: {c} ({d}%)"
                },
                legend: {
                    bottom: '0%',
                    left: 'center',
                    data: ['Jugada','Cabeza','Penal','Tiro Libre']
                },
                series: [{
                    name: 'Goles',
                    type: 'pie',
                    radius: '70%',
                    center: ['50%','50%'],
                    data: [
                        {value: {{ $totalJugada }}, name: 'Jugada'},
                        {value: {{ $totalCabeza }}, name: 'Cabeza'},
                        {value: {{ $totalPenal }}, name: 'Penal'},
                        {value: {{ $totalTiroLibre }}, name: 'Tiro Libre'}
                    ]
                }]
            });
        }
    </script>
@endsection
