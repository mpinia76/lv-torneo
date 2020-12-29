@extends('layouts.app')

@section('pageTitle', 'Editar equipo')

@section('content')
    <div class="container">
    <h1 class="display-6">Editar equipo</h1>

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
    {{ Form::open(['action' => ['EquipoController@update', $equipo->id], 'method' => 'put', 'enctype' => 'multipart/form-data']) }}
    <!-- Include the CRSF token -->
    {{Form::token()}}
    <!-- build our form inputs -->
        <div class="row">


            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('nombre', 'Nombre')}}
                {{Form::text('nombre', $equipo->nombre, ['class' => 'form-control'])}}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('siglas', 'Siglas')}}
                {{Form::text('siglas', $equipo->siglas, ['class' => 'form-control'])}}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('socios', 'Socios')}}
                {{Form::number('socios', $equipo->socios, ['class' => 'form-control'])}}
            </div>
        </div>

        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('fundacion', 'Fundación')}}
                {{Form::date('fundacion', date('Y-m-d', strtotime($equipo->fundacion)), ['class' => 'form-control'])}}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('estadio', 'Estadio')}}
                {{Form::text('estadio', $equipo->estadio, ['class' => 'form-control'])}}
            </div>

        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">
                    Escudo
                    @if($equipo->escudo)
                        <img id="original" src="{{ url('images/'.$equipo->escudo) }}" height="200">
                    @endif
                    <input type="file" name="escudo" class="form-control" placeholder="">

                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-6">
                {{Form::label('historia', 'Historia')}}
                {{Form::textarea('historia', $equipo->historia, ['class' => 'form-control'])}}

            </div>
        </div>

    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{ route('equipos.index') }}" class="btn btn-success">Volver</a>
    {{ Form::close() }}
    </div>
@endsection
