@extends('layouts.app')

@section('pageTitle', 'Editar torneo')

@section('content')
    <div class="container">
    <h1 class="display-6">Editar torneo</h1>

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
    {{ Form::open(['action' => ['TorneoController@update', $torneo->id], 'method' => 'put']) }}
    <!-- Include the CRSF token -->
    {{Form::token()}}
    <!-- build our form inputs -->
    <div class="row">
        <div class="form-group col-xs-12 col-sm-6 col-md-6">
            {{Form::label('nombre', 'Nombre')}}
            {{Form::text('nombre', $torneo->nombre, ['class' => 'form-control'])}}
        </div>

        <div class="form-group col-xs-12 col-sm-6 col-md-3">
            {{Form::label('year', 'Año')}}
            {{Form::text('year', $torneo->year, ['class' => 'form-control'])}}
        </div>
    </div>
    <div class="row">
        <div class="form-group col-xs-12 col-sm-6 col-md-3">
            {{Form::label('equipos', 'Nro. de equipos')}}
            {{Form::number('equipos', $torneo->equipos, ['class' => 'form-control'])}}
        </div>

        <div class="form-group col-xs-12 col-sm-6 col-md-3">
            {{Form::label('grupos', 'Nro. de grupos')}}
            {{Form::number('grupos', $torneo->grupos, ['class' => 'form-control'])}}
        </div>

        <div class="form-group col-xs-12 col-sm-6 col-md-3">
            {{Form::label('playoffs', 'Playoffs')}}
            {{Form::number('playoffs', $torneo->playoffs, ['class' => 'form-control'])}}
        </div>
    </div>

    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{route('torneos.index')}}" class="btn btn-success m-1">Volver</a>
    {{ Form::close() }}
    </div>
@endsection
