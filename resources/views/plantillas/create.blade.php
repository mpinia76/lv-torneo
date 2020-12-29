@extends('layouts.app')

@section('pageTitle', 'Nueva plantilla')

@section('content')
    <div class="container">
    <h1 class="display-6">Nueva plantilla para el torneo {{$torneo->nombre}} {{$torneo->year}}</h1>

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
    {{ Form::open(['action' => 'PlantillaController@store']) }}

    <!-- Include the CRSF token -->
    {{Form::token()}}


    <!-- build our form inputs -->
    <div class="row">
        <div class="form-group col-xs-12 col-sm-6 col-md-2">
            {{Form::label('equipo', 'Equipo')}}
            {{Form::select('equipo_id',$equipos, '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px'])}}
            {{Form::hidden('torneo_id', (isset($_GET['torneoId']))?$_GET['torneoId']:'' )}}
        </div>
        <div class="form-group col-md-12">
        <h1 class="display-6">Jugadores</h1>
            <a class="btn btn-success m-1" href="{{route('jugadores.create',  array('torneoId' => $torneo))}}">Nuevo</a>
        <table class="table" style="width: 50%">
            <thead>
            <th></th>
            <th>Jugador</th>
            <th>Dorsal</th>
            <th><a href="#" class="addRow"><i class="glyphicon glyphicon-plus"></i></a></th>

            </thead>

            <tbody id="cuerpoJugador">
            <tr>
                <td></td>
                <td>{{ Form::select('jugador[]',$jugadors, '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>
                <td>{{Form::number('dorsal[]', '', ['class' => 'form-control', 'size' => '4'])}}</td>

                <td><a href="#" class="btn btn-danger remove"><i class="glyphicon glyphicon-remove"></i></a></td>
            </tr>

            </tbody>




        </table>
        </div>
        <div class="form-group col-md-12">
            <h1 class="display-6">Tecnicos</h1>
            <a class="btn btn-success m-1" href="{{route('tecnicos.create',  array('torneoId' => $torneo))}}">Nuevo</a>
            <table class="table" style="width: 50%">
                <thead>
                <th></th>
                <th>Tecnicos</th>

                <th><a href="#" class="addRowTecnico"><i class="glyphicon glyphicon-plus"></i></a></th>

                </thead>

                <tbody id="cuerpoTecnico">
                <tr>
                    <td></td>
                    <td>{{ Form::select('tecnico[]',$tecnicos, '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>


                    <td><a href="#" class="btn btn-danger removeTecnico"><i class="glyphicon glyphicon-remove"></i></a></td>
                </tr>

                </tbody>




            </table>
        </div>
    </div>

    <!-- build the submission button -->
    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{ route('plantillas.index',array('torneoId'=>$torneo->id)) }}" class="btn btn-success m-1">Volver</a>
    {{ Form::close() }}
    </div>




@endsection


