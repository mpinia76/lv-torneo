@extends('layouts.appPublic')

@section('pageTitle', 'Fecha')

@section('content')
    <div class="container">
        <h1 class="display-6">Fecha {{$fecha->numero}} </h1>

        <hr/>




    <!-- build our form inputs -->
        <div class="row">

            <div class="form-group col-md-12">

                <table class="table" style="width: 70%">
                    <thead>
                    <th>Fecha</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>


                    @foreach($fecha->partidos as $partido)

                    @if($partido->dia)
                    <tr>
                        <td>{{($partido->dia)?date('d/m/Y H:i', strtotime($partido->dia)):''}}</td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipol->id))}}" >
                                @if($partido->equipol)
                                    @if($partido->equipol->escudo)<img id="original" src="{{ url('images/'.$partido->equipol->escudo) }}" height="20">
                                    @endif
                                </a>
                                    {{$partido->equipol->nombre}}
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
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipov->id))}}">
                                @if($partido->equipov)
                                    @if($partido->equipov->escudo)<img id="original" src="{{ url('images/'.$partido->equipov->escudo) }}" height="20">
                                    @endif
                                </a>
                                    {{$partido->equipov->nombre}}
                                @endif
                            </td>
                        <td>
                            <div class="d-flex">

                                <a href="{{route('fechas.detalle', array('partidoId' => $partido->id))}}" class="btn btn-success m-1">Detalles</a>


                            </div>

                        </td>
                        </tr>
                        @endif
                    @endforeach
                </table>
            </div>

        </div>


        <a href="{{ route('fechas.ver',array('grupoId'=>$grupo->id)) }}" class="btn btn-success m-1">Volver</a>

    </div>
@endsection
