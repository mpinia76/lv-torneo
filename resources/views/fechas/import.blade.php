@extends('layouts.app')

@section('pageTitle', 'Importar datos de fechas')

@section('content')
    <div class="container">
    <h1 class="display-6">Importar datos de las fechas del grupo {{$grupo->nombre}} de {{$grupo->torneo->nombre}} {{$grupo->torneo->year}}</h1>

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
    {{ Form::open(['action' => 'FechaController@importprocess', 'enctype' => 'multipart/form-data']) }}

    <!-- Include the CRSF token -->
    {{Form::token()}}


    <!-- build our form inputs -->
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">
                    {{Form::hidden('grupo_id', (isset($_GET['grupoId']))?$_GET['grupoId']:'' )}}
                    Archivo CSV
                    <input type="file" name="archivoCSV" class="form-control" placeholder="">

                </div>
            </div>

        </div>
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{ Form::label('grupoSelect_id', 'Grupo') }}
                {{ Form::select('grupoSelect_id', $grupos, $grupo->id, ['class' => 'form-control']) }}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-6">
                {{ Form::label('url2', 'URL') }}
                {{ Form::text('url2', '', ['class' => 'form-control']) }}
            </div>


        </div>

        <!-- build the submission button -->
        {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{route('fechas.index',array('grupoId'=>$grupo->id)) }}" class="btn btn-success m-1">Volver</a>
        {{ Form::close() }}
    </div>


@endsection

