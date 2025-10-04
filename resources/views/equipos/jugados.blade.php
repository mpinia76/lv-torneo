@extends('layouts.appPublic')

@section('pageTitle', 'Partidos jugados')

@section('content')
    <script type="text/javascript" src="{{ asset('js/echarts.min.js') }}"></script>

    <div class="container py-4">

        <div class="row mb-4 align-items-center">
            <div class="col-md-3 text-center">
                @if($equipo->escudo)
                    <img src="{{ url('images/'.$equipo->escudo) }}" alt="Escudo" class="img-fluid mb-2" style="max-height: 200px;">
                @endif
                <h4>
                    <a href="{{ route('equipos.ver', ['equipoId' => $equipo->id]) }}">
                        {{ $equipo->nombre }}
                        <img src="{{ url('images/'.removeAccents($equipo->pais).'.gif') }}" alt="{{ $equipo->pais }}">
                    </a>
                </h4>
                @if($torneo)
                    <strong>{{ $torneo->getFullNameAttribute() }}</strong>
                @endif
            </div>

            <div class="col-md-9">
                <div class="row text-center">
                    @php
                        $stats = [
                            '' => 'Jugados',
                            'Ganados' => 'Ganados',
                            'Empatados' => 'Empatados',
                            'Perdidos' => 'Perdidos'
                        ];
                        $totales = [$totalJugados, $totalGanados, $totalEmpatados, $totalPerdidos];
                        $i = 0;
                    @endphp
                    @foreach($stats as $key => $label)
                        <div class="col-6 col-md-3 mb-3">
                            <a href="{{ $torneo
                                ? route('equipos.jugados', ['equipoId'=>$equipo->id, 'torneoId'=>$torneo->id, 'tipo'=>$key])
                                : route('equipos.jugados', ['equipoId'=>$equipo->id, 'tipo'=>$key]) }}"
                               class="text-decoration-none">
                                <div class="card {{ $tipo==$key ? 'bg-success text-white' : '' }}">
                                    <div class="card-body p-2">
                                        <h6 class="mb-1">{{ $label }}</h6>
                                        <h5 class="mb-0">{{ $totales[$i++] }}</h5>
                                    </div>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>

                @if($tipo=='')
                    <div class="card mt-3">
                        <div class="card-body">
                            <div id="pie_basic" style="height: 300px;"></div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle text-center">
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
                            <tr class="clickable-row"
                                data-href="{{ route('fechas.detalle', ['partidoId'=>$partido->partido_id]) }}"
                                style="cursor:pointer;">
                                <td>{{ $partido->nombreTorneo }} {{ $partido->year }}</td>
                                <td>{{ is_numeric($partido->numero) ? 'Fecha '.$partido->numero : $partido->numero }}</td>
                                <td>{{ $partido->dia ? date('d/m/Y H:i', strtotime($partido->dia)) : '' }}</td>
                                <td class="text-end">
                                    @if($partido->local)
                                        @if($partido->fotoLocal)
                                            <img src="{{ url('images/'.$partido->fotoLocal) }}" height="20" class="me-1">
                                        @endif
                                        {{ $partido->local }}
                                        <img src="{{ url('images/'.removeAccents($partido->paisLocal).'.gif') }}" alt="{{ $partido->paisLocal }}" height="15">
                                    @endif
                                </td>
                                <td class="fw-bold">{{ $partido->golesl }}@if(isset($partido->penalesl)) ({{ $partido->penalesl }}) @endif</td>
                                <td class="fw-bold">{{ $partido->golesv }}@if(isset($partido->penalesv)) ({{ $partido->penalesv }}) @endif</td>
                                <td class="text-start">
                                    @if($partido->visitante)
                                        @if($partido->fotoVisitante)
                                            <img src="{{ url('images/'.$partido->fotoVisitante) }}" height="20" class="me-1">
                                        @endif
                                        {{ $partido->visitante }}
                                        <img src="{{ url('images/'.removeAccents($partido->paisVisitante).'.gif') }}" alt="{{ $partido->paisVisitante }}" height="15">
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div>{{ $partidos->links() }}</div>
                    <div><strong>Total: {{ $partidos->total() }}</strong></div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-start mt-4">
            <a href="{{ url()->previous() }}" class="btn btn-success">Volver</a>
        </div>

    </div>

    {{-- Script de ECharts --}}
    <script>
        var pie_basic_element = document.getElementById('pie_basic');
        if (pie_basic_element) {
            var pie_basic = echarts.init(pie_basic_element);
            pie_basic.setOption({
                color: ['#26eb0e','#e5cf0d','#f90a23'],
                textStyle: { fontFamily: 'Roboto, Arial, Verdana, sans-serif', fontSize: 13 },
                tooltip: { trigger: 'item', backgroundColor: 'rgba(0,0,0,0.75)', padding: [10, 15], formatter: "{b}: {c} ({d}%)" },
                legend: { orient: 'horizontal', bottom: '0%', left: 'center', data: ['Ganados','Empatados','Perdidos'], itemHeight: 8, itemWidth: 8 },
                series: [{
                    name: 'Partidos',
                    type: 'pie',
                    radius: '70%',
                    center: ['50%', '50%'],
                    itemStyle: { borderWidth: 1, borderColor: '#fff' },
                    data: [
                        {value: {{$totalGanados}}, name: 'Ganados'},
                        {value: {{$totalEmpatados}}, name: 'Empatados'},
                        {value: {{$totalPerdidos}}, name: 'Perdidos'}
                    ]
                }]
            });
        }

        // Filas clickeables
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.clickable-row').forEach(row => {
                row.addEventListener('click', () => {
                    window.location.href = row.dataset.href;
                });
            });
        });
    </script>
@endsection
