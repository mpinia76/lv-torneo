@extends('layouts.app')

@section('pageTitle', 'Datos complementarios')

@section('content')
    <div class="container">
        <h1 class="display-6">Datos complementarios de @if(is_numeric($fecha->numero))
                Fecha {{ $fecha->numero }}
            @else
                {{ $fecha->numero }}@endif del grupo {{$grupo->nombre}} de {{$grupo->torneo->nombre}} {{$grupo->torneo->year}}</h1>

        <hr/>
        <!-- if validation in the controller fails, show the errors -->
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
    @endif
        @if (\Session::has('error'))
            <div class="alert alert-danger">
                <ul>
                    <li>{!! \Session::get('error') !!}</li>
                </ul>
            </div>
        @endif
        @if (\Session::has('success'))
            <div class="alert alert-success">
                <ul>
                    <li>{!! \Session::get('success') !!}</li>
                </ul>
            </div>
    @endif



    <!-- build our form inputs -->
        <div class="row">

            <div class="form-group col-md-12">
                <h1 class="display-6">Partidos</h1>
                <table class="table">
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
                            <td>@if($partido->equipol)
                                    @if($partido->equipol->escudo)<img id="original" src="{{ url('images/'.$partido->equipol->escudo) }}" height="20">
                                    @endif
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
                            <td>@if($partido->equipov)
                                    @if($partido->equipov->escudo)<img id="original" src="{{ url('images/'.$partido->equipov->escudo) }}" height="20">
                                    @endif
                                    {{$partido->equipov->nombre}} <img id="original" src="{{ $partido->equipov->bandera_url }}" alt="{{ $partido->equipov->pais }}">
                                @endif
                            </td>
                        <td>
                            <div class="d-flex">

                                <a href="{{route('alineaciones.index', array('partidoId' => $partido->id))}}" class="btn btn-success m-1">Alineaciones</a>
                                <a href="{{route('goles.index', array('partidoId' => $partido->id, 'totalGoles' => $partido->golesl+$partido->golesv))}}" class="btn btn-info m-1">Goles</a>

                                <a href="{{route('tarjetas.index', array('partidoId' => $partido->id))}}" class="btn btn-primary m-1">Tarjetas</a>
                                <a href="{{route('partidos.arbitros', array('partidoId' => $partido->id))}}" class="btn btn-success m-1">Jueces</a>
                                <a href="{{route('cambios.index', array('partidoId' => $partido->id))}}" class="btn btn-primary m-1">Sustituciones</a>
                                <a href="{{route('fechas.importarPartido', array('partidoId' => $partido->id))}}" class="btn btn-info m-1">Importar</a>
                            </div>

                        </td>
                        </tr>
                        @endif
                    @endforeach
                </table>
            </div>

        </div>


        <a href="{{ route('fechas.index',array('grupoId'=>$grupo->id)) }}" class="btn btn-success m-1">Volver</a>

    </div>
@endsection
