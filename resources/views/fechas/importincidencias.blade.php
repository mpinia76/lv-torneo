@extends('layouts.app')

@section('pageTitle', 'Importar datos de partidos')

@section('content')
    <div class="container">
    <h1 class="display-6">Importar incidencias de los partidos del grupo {{$grupo->nombre}} de {{$grupo->torneo->nombre}} {{$grupo->torneo->year}}</h1>

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
    {{ Form::open(['action' => 'FechaController@importincidenciasprocess', 'enctype' => 'multipart/form-data']) }}

    <!-- Include the CRSF token -->
    {{Form::token()}}


    <!-- build our form inputs -->
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">
                    {{Form::hidden('grupo_id', (isset($_GET['grupoId']))?$_GET['grupoId']:'' )}}
                    Archivo HTML
                    <input type="file" name="archivoHTML" class="form-control" placeholder="">

                </div>
            </div>

        </div>

        <!-- build the submission button -->
        {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{route('fechas.index',array('grupoId'=>$grupo->id)) }}" class="btn btn-success m-1">Volver</a>
        {{ Form::close() }}
    </div>


@endsection

