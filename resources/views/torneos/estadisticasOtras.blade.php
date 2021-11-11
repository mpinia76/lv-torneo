@extends('layouts.appPublic')

@section('pageTitle', 'Otras estadísticas')

@section('content')
    <div class="container">


        <hr/>




        <br>

        <div class="row">

            <div class="form-group col-md-12">
                <h1>Fechas con más goles</h1>
                <table class="table" style="width: 100%">
                    <thead>
                    <th>Torneo</th>
                    <th>Fecha</th>

                    <th>Goles</th>
                    <th>Promedio</th>
                    <th>Partidos</th>


                    </thead>
                    <tbody>

                    @foreach($estadisticas['fechaMasGoles'] as $partido)
                        <tr>
                            <td>{{$partido->nombreTorneo}} {{$partido->year}}</td>
                            <td>Fecha {{$partido->numero}}</td>

                            <td>{{$partido->goles}}</td>
                            <td>{{$partido->promedio}}</td>
                            <td>{{$partido->partidos}}</td>



                        </tr>
                    @endforeach
                    </tbody>


                </table>

                <h1>Fechas con más goles locales</h1>
                <table class="table" style="width: 100%">
                    <thead>
                    <th>Torneo</th>
                    <th>Fecha</th>

                    <th>Goles</th>
                    <th>Promedio</th>
                    <th>Partidos</th>

                    </thead>
                    <tbody>

                    @foreach($estadisticas['fechaMasGolesLocales'] as $partido)
                        <tr>
                            <td>{{$partido->nombreTorneo}} {{$partido->year}}</td>
                            <td>Fecha {{$partido->numero}}</td>

                            <td>{{$partido->goles}}</td>
                            <td>{{$partido->promedio}}</td>
                            <td>{{$partido->partidos}}</td>



                        </tr>
                    @endforeach
                    </tbody>


                </table>

                <h1>Fechas con más goles visitantes</h1>
                <table class="table" style="width: 100%">
                    <thead>
                    <th>Torneo</th>
                    <th>Fecha</th>

                    <th>Goles</th>
                    <th>Promedio</th>
                    <th>Partidos</th>

                    </thead>
                    <tbody>

                    @foreach($estadisticas['fechaMasGolesVisitantes'] as $partido)
                        <tr>
                            <td>{{$partido->nombreTorneo}} {{$partido->year}}</td>
                            <td>Fecha {{$partido->numero}}</td>

                            <td>{{$partido->goles}}</td>
                            <td>{{$partido->promedio}}</td>
                            <td>{{$partido->partidos}}</td>


                        </tr>
                    @endforeach
                    </tbody>


                </table>


                <h1>Partidos con más goles</h1>
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

                    @foreach($estadisticas['maxGoles'] as $partido)
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


                <h1>Partidos con más goles locales</h1>
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

                    @foreach($estadisticas['maxGolesLocales'] as $partido)
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
                <h1>Partidos con más goles visitantes</h1>
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

                    @foreach($estadisticas['maxGolesVisitantes'] as $partido)
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


            </div>

        </div>
        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>

@endsection
