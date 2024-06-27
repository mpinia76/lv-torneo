@extends('layouts.app')

@section('pageTitle', 'Editar incidencia')

@section('content')
    <div class="container">
    <h1 class="display-6">Editar incidencia para el torneo {{$torneo->nombre}} {{$torneo->year}}</h1>

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
    {{ Form::open(['action' => ['IncidenciaController@update', $incidencia->id], 'method' => 'put']) }}
    <!-- Include the CRSF token -->
    {{Form::token()}}
        {{Form::hidden('torneo_id', $torneo->id)}}
        <!-- build our form inputs -->
        <div class="row">


            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                {{Form::label('equipo', 'Equipo')}}
                {{Form::select('equipo_id',$equipos, $incidencia->equipo_id,['class' => 'form-control js-example-basic-single'])}}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('puntos', 'Puntos')}}
                {{Form::number('puntos', $incidencia->puntos, ['class' => 'form-control'])}}
            </div>

        </div>

        <div class="row">


            <div class="form-group col-xs-12 col-sm-6 col-md-6">
                {{Form::label('partido', 'Partido')}}
                {{Form::select('partido_id',$partidos, $incidencia->partido_id,['class' => 'form-control js-example-basic-single'])}}
            </div>



        </div>

        <div class="row">


            <div class="form-group col-xs-12 col-sm-6 col-md-6">
                {{Form::label('observaciones', 'Observaciones')}}
                {{Form::textarea('observaciones', $incidencia->observaciones, ['class' => 'form-control'])}}

            </div>
        </div>

    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{ route('arbitros.index')}}" class="btn btn-success">Volver</a>
    {{ Form::close() }}
    </div>
@endsection
