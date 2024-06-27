@extends('layouts.app')

@section('pageTitle', 'Nuevo equipo')

@section('content')
    <div class="container">
    <h1 class="display-6">Nuevo equipo</h1>

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
    {{ Form::open(['action' => 'EquipoController@store', 'enctype' => 'multipart/form-data']) }}

    <!-- Include the CRSF token -->
    {{Form::token()}}


    <!-- build our form inputs -->
    <div class="row">


        <div class="form-group col-xs-12 col-sm-6 col-md-3">
            {{Form::label('nombre', 'Nombre')}}
            {{Form::text('nombre', '', ['class' => 'form-control'])}}
        </div>

        <div class="form-group col-xs-12 col-sm-6 col-md-3">
            {{Form::label('siglas', 'Siglas')}}
            {{Form::text('siglas', '', ['class' => 'form-control'])}}
        </div>

        <div class="form-group col-xs-12 col-sm-6 col-md-3">
            {{Form::label('socios', 'Socios')}}
            {{Form::number('socios', '', ['class' => 'form-control'])}}
        </div>
    </div>

        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('fundacion', 'Fundación')}}
                {{Form::date('fundacion', '', ['class' => 'form-control'])}}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('estadio', 'Estadio')}}
                {{Form::text('estadio', '', ['class' => 'form-control'])}}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('pais', 'País')}}
                {{Form::text('pais', '', ['class' => 'form-control'])}}
            </div>
        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">
                    Foto
                    <input type="file" name="escudo" class="form-control" placeholder="">

                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-6">
                {{Form::label('historia', 'Historia')}}
                {{Form::textarea('historia', '', ['class' => 'form-control'])}}

            </div>
        </div>

    <!-- build the submission button -->
    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{ route('equipos.index')}}" class="btn btn-success">Volver</a>
    {{ Form::close() }}
    </div>
@endsection
