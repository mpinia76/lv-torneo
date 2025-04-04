@extends('layouts.appPublic')

@section('pageTitle', 'Fecha')

@section('content')
    <div class="container">
        <h1 class="display-6">
            @if(is_numeric($fecha->numero))
                Fecha {{ $fecha->numero }}
            @else
                {{ $fecha->numero }}
            @endif

            </h1>

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
                                    {{$partido->equipol->nombre}} <img id="original" src="{{ $partido->equipol->bandera_url }}" alt="{{ $partido->equipol->pais }}">
                                @endif
                            </td>
                            <td>{{$partido->golesl}}
                                @if(isset($partido->penalesl))
                                    ({{$partido->penalesl}})
                                @endif
                            </td>
                            <td>{{$partido->golesv}}
                                @if(isset($partido->penalesv))
    ({{$partido->penalesv}})
@endif
                            </td>
                            <td>
                                <a href="{{route('equipos.ver', array('equipoId' => $partido->equipov->id))}}">
                                @if($partido->equipov)
                                    @if($partido->equipov->escudo)<img id="original" src="{{ url('images/'.$partido->equipov->escudo) }}" height="20">
                                    @endif
                                </a>
                                    {{$partido->equipov->nombre}} <img id="original" src="{{ $partido->equipov->bandera_url }}" alt="{{ $partido->equipov->pais }}">
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


        <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>

    </div>
@endsection
