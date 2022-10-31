@extends('layouts.appPublic')

@section('pageTitle', 'Partidos jugados')

@section('content')
    <div class="container">

        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-4">
                        <div class="form-group">

                            @if($jugador->persona->foto)
                                <img id="original" src="{{ url('images/'.$jugador->persona->foto) }}" height="200">
                            @else
                                <img id="original" src="{{ url('images/sin_foto.png') }}" height="200">
                            @endif


                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-8">
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Jugados</dt>
                        <dd>{{$totalJugados}}</dd>
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Ganados</dt>
                        <dd>{{$totalGanados}}</dd>
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Empatados</dt>
                        <dd>{{$totalEmpatados}}</dd>
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-3">
                        <dt>Perdidos</dt>
                        <dd>{{$totalPerdidos}}</dd>

                    </div>


                </div>


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
                {{$partidos->links()}}
            </div>
        </div>


    <div class="d-flex">

        <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
    </div>
    </div>

@endsection
