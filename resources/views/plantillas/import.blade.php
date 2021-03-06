@extends('layouts.app')

@section('pageTitle', 'Importar plantilla')

@section('content')
    <div class="container">
    <h1 class="display-6">Importar plantilla del grupo {{$grupo->nombre}} de {{$grupo->torneo->nombre}} {{$grupo->torneo->year}}</h1>

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
    {{ Form::open(['action' => 'PlantillaController@importprocess']) }}

    <!-- Include the CRSF token -->
    {{Form::token()}}


    <!-- build our form inputs -->
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">
                    {{Form::label('equipo', 'Equipo')}}
                    {{Form::select('equipo_id',$equipos, '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px'])}}

                    {{Form::hidden('grupo_id', (isset($_GET['grupoId']))?$_GET['grupoId']:'' )}}


                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">
                      {{Form::label('torneo', 'Torneo')}}
                    {{Form::select('torneo_id',$torneosAnteriores, '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px'])}}



                </div>
            </div>

        </div>

        <!-- build the submission button -->
        {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{route('plantillas.create',array('grupoId'=>$grupo->id)) }}" class="btn btn-success m-1">Volver</a>
        {{ Form::close() }}
    </div>


@endsection

