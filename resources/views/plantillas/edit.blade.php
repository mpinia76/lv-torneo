@extends('layouts.app')

@section('pageTitle', 'Editar plantilla')

@section('content')
    <div class="container">
        <h1 class="display-6">Plantilla de {{$plantilla->equipo->nombre}} en {{$torneo->nombre}} {{$torneo->year}}</h1>
        <br>
        @if($plantilla->equipo->escudo)
            <img id="original" src="{{ url('images/'.$plantilla->equipo->escudo) }}" height="50">
        @endif
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

    <!-- Open the form with the store function route. -->
    {{ Form::open(['action' => ['PlantillaController@update', $plantilla->id], 'method' => 'put']) }}
    <!-- Include the CRSF token -->
    {{Form::token()}}
    <!-- build our form inputs -->
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('equipo', 'Equipo')}}
                {{Form::select('equipo_id',$equipos, $plantilla->equipo->id,['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px'])}}

                {{Form::hidden('torneo_id', (isset($_GET['torneoId']))?$_GET['torneoId']:$plantilla->torneo->id )}}
            </div>
            <div class="form-group col-md-12">
                <h1 class="display-6">Jugadores</h1>
                <a class="btn btn-success m-1" href="{{route('jugadores.create',  array('plantillaId' => $plantilla->id))}}">Nuevo</a>

                <table class="table" style="width: 50%">
                    <thead>
                    <th></th>
                    <th>Jugador</th>
                    <th>Dorsal</th>
                    <th><a href="#" class="addRow"><i class="glyphicon glyphicon-plus"></i></a></th>

                    </thead>
                    <tbody id="cuerpoJugador">
                    @php
                        $i = 1;
                    @endphp
                    @foreach($plantillaJugadors ?? '' as $plantillaJugador)

                        <tr>
                            <td>
                                {{$i++}}
                                @if($plantillaJugador->jugador->foto)
                                    <img id="original" src="{{ url('images/'.$plantillaJugador->jugador->foto) }}" height="50">
                                @else
                                    <img id="original" src="{{ url('images/sin_foto.png') }}" height="50">
                                @endif
                                {{Form::hidden('plantillajugador_id[]',$plantillaJugador->id)}}
                            </td>

                            <td>{{ Form::select('jugador[]',$jugadors, $plantillaJugador->jugador->id,['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>
                            <td>{{Form::number('dorsal[]', $plantillaJugador->dorsal, ['class' => 'form-control', 'size' => '4'])}}</td>

                            <td><a href="#" class="btn btn-danger remove"><i class="glyphicon glyphicon-remove"></i></a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="form-group col-md-12">
                <h1 class="display-6">Tecnicos</h1>
                <a class="btn btn-success m-1" href="{{route('tecnicos.create',  array('plantillaId' => $plantilla->id))}}">Nuevo</a>

                <table class="table" style="width: 50%">
                    <thead>
                    <th></th>
                    <th>Técnico</th>

                    <th><a href="#" class="addRowTecnico"><i class="glyphicon glyphicon-plus"></i></a></th>

                    </thead>
                    <tbody id="cuerpoTecnico">

                    @foreach($plantillaTecnicos ?? '' as $plantillaTecnico)

                        <tr>
                            <td>@if($plantillaTecnico->tecnico->foto)
                                    <img id="original" src="{{ url('images/'.$plantillaTecnico->tecnico->foto) }}" height="50">
                                @else
                                    <img id="original" src="{{ url('images/sin_foto_tecnico.png') }}" height="50">
                                @endif
                            {{Form::hidden('plantillatecnico_id[]',$plantillaTecnico->id)}}</td>
                            <td>{{ Form::select('tecnico[]',$tecnicos, $plantillaTecnico->tecnico->id,['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>


                            <td><a href="#" class="btn btn-danger removeTecnico"><i class="glyphicon glyphicon-remove"></i></a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}

        <a href="{{ route('plantillas.index',array('torneoId'=>$torneo->id))  }}" class="btn btn-success m-1">Volver</a>
    {{ Form::close() }}


@endsection
