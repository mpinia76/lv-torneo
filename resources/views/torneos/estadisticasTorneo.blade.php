@extends('layouts.appPublic')

@section('pageTitle', 'Otras estadísticas')

@section('content')
    <div class="container">


        <hr/>




        <br>

        <div class="row">

            <div class="form-group col-md-12">
                <h1>Goles</h1>
                <table class="table" style="width: 100%">
                    <thead>
                    <th>Partidos</th>
                    <th>Total</th>

                    <th>Promedio</th>
                    <th>Locales</th>
                    <th>Promedio</th>
                    <th>Visitante</th>
                    <th>Promedio</th>

                    </thead>
                    <tbody>

                    @foreach($estadisticas['goles'] as $partido)
                        <tr>

                            <td>{{$partido->partidos}}</td>

                            <td>{{$partido->total}}</td>
                            <td>{{$partido->promedio}}</td>

                            <td>{{$partido->local}}</td>
                            <td>{{$partido->promediolocal}}</td>
                            <td>{{$partido->visitante}}</td>
                            <td>{{$partido->promediovisitante}}</td>

                        </tr>
                    @endforeach
                    </tbody>


                </table>
                <h1>Fechas con más goles</h1>
                <table class="table" style="width: 100%">
                    <thead>
                    <th>#</th>
                    <th>Fecha</th>

                    <th>Goles</th>
                    <th>Promedio</th>
                    <th>Partidos</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['fechaMasGoles'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
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
                    <th>#</th>
                    <th>Fecha</th>

                    <th>Goles</th>
                    <th>Promedio</th>
                    <th>Partidos</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['fechaMasGolesLocales'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
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
                    <th>#</th>
                    <th>Fecha</th>

                    <th>Goles</th>
                    <th>Promedio</th>
                    <th>Partidos</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['fechaMasGolesVisitantes'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
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
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['maxGoles'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
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
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['maxGolesLocales'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
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
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['maxGolesVisitantes'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
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
                <h1>Fechas con menos goles</h1>
                <table class="table" style="width: 100%">
                    <thead>
                    <th>#</th>
                    <th>Fecha</th>

                    <th>Goles</th>
                    <th>Promedio</th>
                    <th>Partidos</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['fechaMinGoles'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
                            <td>Fecha {{$partido->numero}}</td>

                            <td>{{$partido->goles}}</td>
                            <td>{{$partido->promedio}}</td>

                            <td>{{$partido->partidos}}</td>

                        </tr>
                    @endforeach
                    </tbody>


                </table>



                <h1>Fechas con menos goles locales</h1>
                <table class="table" style="width: 100%">
                    <thead>
                    <th>#</th>
                    <th>Fecha</th>

                    <th>Goles</th>
                    <th>Promedio</th>
                    <th>Partidos</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['fechaMinGolesLocales'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
                            <td>Fecha {{$partido->numero}}</td>

                            <td>{{$partido->goles}}</td>
                            <td>{{$partido->promedio}}</td>

                            <td>{{$partido->partidos}}</td>

                        </tr>
                    @endforeach
                    </tbody>


                </table>

                <h1>Fechas con menos goles visitantes</h1>
                <table class="table" style="width: 100%">
                    <thead>
                    <th>#</th>
                    <th>Fecha</th>

                    <th>Goles</th>
                    <th>Promedio</th>
                    <th>Partidos</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['fechaMinGolesVisitantes'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
                            <td>Fecha {{$partido->numero}}</td>

                            <td>{{$partido->goles}}</td>
                            <td>{{$partido->promedio}}</td>

                            <td>{{$partido->partidos}}</td>

                        </tr>
                    @endforeach
                    </tbody>


                </table>


                <h1>Partidos con menos goles</h1>
                <table class="table" style="width: 100%">
                    <thead>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['minGoles'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
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




                <h1>Partidos con menos goles locales</h1>
                <table class="table" style="width: 100%">
                    <thead>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['minGolesLocales'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
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
                <h1>Partidos con menos goles visitantes</h1>
                <table class="table" style="width: 100%">
                    <thead>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>
                    <tbody>
                    @php
                        $i = 1;
                    @endphp
                    @foreach($estadisticas['minGolesVisitantes'] as $partido)
                        <tr>
                            <td>{{$i++}}</td>
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
