@extends('layouts.appPublic')

@section('pageTitle', 'Partidos jugados')

@section('content')
    <script type="text/javascript" src="{{asset('js/echarts.min.js')}}"></script>
    <div class="container">

        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                @if($torneo)
                    <div class="row">
                        <div class="form-group col-xs-12 col-sm-6 col-md-10">
                            <div class="form-group">

                                <strong>{{$torneo->getFullNameAttribute()}}</strong>


                            </div>
                        </div>
                    </div>
                @endif
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-4">
                        <div class="form-group">

                            @if($equipo->escudo)
                                <img id="original" src="{{ url('images/'.$equipo->escudo) }}" height="200">
                            @endif


                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-10">
                        <div class="form-group">

                            <a href="{{route('equipos.ver', array('equipoId' => $equipo->id))}}" ><strong>{{$equipo->nombre}}</strong></a>


                        </div>
                    </div>
                </div>

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-8" id="detalle">

                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        @if($torneo)
                            <a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId' => $torneo->id))}}" >
                                @else
                                    <a href="{{route('equipos.jugados', array('equipoId' => $equipo->id))}}" >
                                        @endif
                        <dt <?php echo ($tipo=='')? 'style="background: #4caf50; color: #ffffff"':''; ?>>Jugados</dt>
                        <dd <?php echo ($tipo=='')? 'style="background: #4caf50; color: #ffffff"':''; ?>>{{$totalJugados}}</dd>
                                    </a>
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        @if($torneo!='')
                            <a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId' => $torneo->id,'tipo' => 'Ganados'))}}" >
                                @else
                                    <a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'tipo' => 'Ganados'))}}" >
                                        @endif
                        <dt <?php echo ($tipo=='Ganados')? 'style="background: #4caf50; color: #ffffff"':''; ?>>Ganados</dt>
                        <dd <?php echo ($tipo=='Ganados')? 'style="background: #4caf50; color: #ffffff"':''; ?>>{{$totalGanados}}</dd></a>
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        @if($torneo!='')
                            <a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId' => $torneo->id,'tipo' => 'Empatados'))}}" >
                                @else
                                    <a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'tipo' => 'Empatados'))}}" >
                                        @endif
                        <dt <?php echo ($tipo=='Empatados')? 'style="background: #4caf50; color: #ffffff"':''; ?>>Empatados</dt>
                        <dd <?php echo ($tipo=='Empatados')? 'style="background: #4caf50; color: #ffffff"':''; ?>>{{$totalEmpatados}}</dd>
                                    </a>
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        @if($torneo!='')
                            <a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'torneoId' => $torneo->id,'tipo' => 'Perdidos'))}}" >
                                @else
                                    <a href="{{route('equipos.jugados', array('equipoId' => $equipo->id,'tipo' => 'Perdidos'))}}" >
                                        @endif
                        <dt <?php echo ($tipo=='Perdidos')? 'style="background: #4caf50; color: #ffffff"':''; ?>>Perdidos</dt>
                        <dd <?php echo ($tipo=='Perdidos')? 'style="background: #4caf50; color: #ffffff"':''; ?>>{{$totalPerdidos}}</dd>
                                    </a>

                    </div>


                </div>
                @if($tipo=='')
                <div class="row">
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

        <div class="row">

            <div class="form-group col-md-12">

                <table class="table" style="width: 100%">
                    <thead>
                    <th>Torneo</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>
                    <tbody>

                    @foreach($partidos as $partido)
                        <tr>
                            <td>{{$partido->nombreTorneo}} {{$partido->year}}</td>
                            <td>Fecha {{$partido->numero}}</td>
                            <td>{{($partido->dia)?date('d/m/Y H:i', strtotime($partido->dia)):''}}</td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipol_id))}}" >
                                    @if($partido->local)
                                        @if($partido->fotoLocal)<img id="original" src="{{ url('images/'.$partido->fotoLocal) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->local}}
                                @endif
                            </td>
                            <td>{{$partido->golesl}}
                                @if($partido->penalesl)
                                    ({{$partido->penalesl}})
                                @endif
                            </td>
                            <td>{{$partido->golesv}}
                                @if($partido->penalesv)
                                    ({{$partido->penalesv}})
                                @endif
                            </td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipov_id))}}">
                                    @if($partido->visitante)
                                        @if($partido->fotoVisitante)<img id="original" src="{{ url('images/'.$partido->fotoVisitante) }}" height="20">
                                        @endif
                                </a>
                                {{$partido->visitante}}
                                @endif
                            </td>
                            <td>
                                <div class="d-flex">

                                    <a href="{{route('fechas.detalle', array('partidoId' => $partido->partido_id))}}" class="btn btn-success m-1">Detalles</a>


                                </div>

                            </td>


                        </tr>
                    @endforeach
                    </tbody>


                </table>
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-9">
                        {{ $partidos->links() }}
                    </div>

                    <div class="form-group col-xs-12 col-sm-6 col-md-2">
                        <strong>Total: {{ $partidos->total() }}</strong>
                    </div>
                </div>

            </div>
        </div>


    <div class="d-flex">

        <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
    </div>
    </div>
    <script type="text/javascript">
        var pie_basic_element = document.getElementById('pie_basic');
        if (pie_basic_element) {
            var pie_basic = echarts.init(pie_basic_element);
            pie_basic.setOption({
                color: [
                    '#26eb0e','#e5cf0d','#f90a23','#ffb980','#d87a80',
                    '#8d98b3','#e5cf0d','#97b552','#95706d','#dc69aa',
                    '#07a2a4','#9a7fd1','#588dd5','#f5994e','#c05050',
                    '#59678c','#c9ab00','#7eb00a','#6f5553','#c14089'
                ],

                textStyle: {
                    fontFamily: 'Roboto, Arial, Verdana, sans-serif',
                    fontSize: 13
                },

                title: {
                    text: '',
                    left: 'center',
                    textStyle: {
                        fontSize: 17,
                        fontWeight: 500
                    },
                    subtextStyle: {
                        fontSize: 12
                    }
                },

                tooltip: {
                    trigger: 'item',
                    backgroundColor: 'rgba(0,0,0,0.75)',
                    padding: [10, 15],
                    textStyle: {
                        fontSize: 13,
                        fontFamily: 'Roboto, sans-serif'
                    },
                    formatter: "{a} <br/>{b}: {c} ({d}%)"
                },

                legend: {
                    orient: 'horizontal',
                    bottom: '0%',
                    left: 'center',
                    data: ['Ganados', 'Empatados','Perdidos'],
                    itemHeight: 8,
                    itemWidth: 8
                },

                series: [{
                    name: 'Partidos',
                    type: 'pie',
                    radius: '70%',
                    center: ['50%', '50%'],
                    itemStyle: {
                        normal: {
                            borderWidth: 1,
                            borderColor: '#fff'
                        }
                    },
                    data: [
                        {value: {{$totalGanados}}, name: 'Ganados'},
                        {value: {{$totalEmpatados}}, name: 'Empatados'},
                        {value: {{$totalPerdidos}}, name: 'Perdidos'}
                    ]
                }]
            });
        }
    </script>
@endsection
