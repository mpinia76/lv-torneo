@extends('layouts.app')

@section('pageTitle', 'Editar plantilla')

@section('content')
    <div class="container">
        <h1 class="display-6">Plantilla de {{$plantilla->equipo->nombre}} en {{$grupo->torneo->nombre}} {{$grupo->torneo->year}}</h1>
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

    <!-- Open the form with the store function route. -->
    {{ Form::open(['action' => ['PlantillaController@update', $plantilla->id], 'method' => 'put']) }}
    <!-- Include the CRSF token -->
    {{Form::token()}}
    <!-- build our form inputs -->
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('equipo', 'Equipo')}}
                {{Form::select('equipo_id',$equipos, $plantilla->equipo->id,['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px'])}}

                {{Form::hidden('grupo_id', (isset($_GET['grupoId']))?$_GET['grupoId']:$plantilla->grupo->id )}}
                {{Form::hidden('cantJugadors', count($plantillaJugadors),['id' => 'cantJugadors'])}}
            </div>

            <div class="form-group col-md-12">
                <h1 class="display-6">Jugadores</h1>
                <a class="btn btn-success m-1" href="{{route('jugadores.create',  array('plantillaId' => $plantilla->id))}}">Nuevo</a>
                <a class="btn btn-info m-1" href="{{route('plantilla.importar',  array('plantillaId' => $plantilla->id))}}">Importar</a>
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
                                @if($plantillaJugador->jugador->persona->foto)
                                    <img id="original" class="imgCircle" src="{{ url('images/'.$plantillaJugador->jugador->persona->foto) }}" >
                                @else
                                    <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                @endif
                                {{Form::hidden('plantillajugador_id[]',$plantillaJugador->id)}}
                            </td>

                            <td>{{ Form::select('jugador[]',$jugadors, $plantillaJugador->jugador->id,['id'=>'jugador'.$i,'class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>
                            <td>{{Form::number('dorsal[]', $plantillaJugador->dorsal, ['class' => 'form-control', 'style' => 'width:70px;'])}}</td>

                            <td><a href="#" class="btn btn-danger remove"><i class="glyphicon glyphicon-remove"></i></a></td>
                        </tr>

                    @endforeach
                    </tbody>
                    <thead>
                    <th></th>
                    <th>Jugador</th>
                    <th>Dorsal</th>
                    <th><a href="#" class="addRow"><i class="glyphicon glyphicon-plus"></i></a></th>

                    </thead>
                </table>
                <a class="btn btn-success m-1" href="{{route('jugadores.create',  array('plantillaId' => $plantilla->id))}}">Nuevo</a>
            </div>

        </div>

    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}

        <a href="{{ route('plantillas.index',array('grupoId'=>$grupo->id))  }}" class="btn btn-success m-1">Volver</a>
    {{ Form::close() }}


@endsection
