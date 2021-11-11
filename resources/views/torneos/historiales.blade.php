@extends('layouts.appPublic')

@section('pageTitle', 'Historiales')

@section('content')
    <div class="container">


        <hr/>



        <form class="form-inline">


            <select class="orm-control js-example-basic-single" id="equipo1" name="equipo1" onchange="this.form.submit()" style="width: 150px">
                @foreach($equipos as $equipo)

                    <option value="{{$equipo->id}}" @if($equipo->id==$e1->id)
                    selected

                        @endif />{{$equipo->nombre}}</option>
                @endforeach

            </select>
            VS.
            <select class="orm-control js-example-basic-single" id="equipo2" name="equipo2" onchange="this.form.submit()" style="width: 150px">
                @foreach($equipos as $equipo)

                    <option value="{{$equipo->id}}" @if($equipo->id==$e2->id)
                    selected

                        @endif />{{$equipo->nombre}}</option>
                @endforeach

            </select>


        </form>
        <br>

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




                <table class="table">
                    <thead>

                    <th>Equipo</th>
                    <th>J</th>
                    <th>G</th>
                    <th>E</th>
                    <th>P</th>
                    <th>GF</th>
                    <th>GC</th>
                    <th>Dif.</th>
                    <th>Punt.</th>

                    </thead>
                    <tbody>

                    @foreach($posiciones as $equipo)
                        <tr>

                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $equipo->equipo_id))}}" >
                                @if($equipo->foto)
                                    <img id="original" src="{{ url('images/'.$equipo->foto) }}" height="25">
                                @endif
                                </a>
                                {{$equipo->equipo}}</td>
                            <td>{{$equipo->jugados}}</td>
                            <td>{{$equipo->ganados}}</td>
                            <td>{{$equipo->empatados}}</td>
                            <td>{{$equipo->perdidos}}</td>
                            <td>{{$equipo->golesl}}</td>
                            <td>{{$equipo->golesv}}</td>
                            <td>{{$equipo->diferencia}}</td>
                            <td>{{$equipo->puntaje}}</td>



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
