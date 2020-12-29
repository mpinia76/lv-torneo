@extends('layouts.app')

@section('pageTitle', 'Nuevo jugador')

@section('content')
    <div class="container">
    <h1 class="display-6">Nuevo jugador</h1>

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
    {{ Form::open(['action' => 'JugadorController@store', 'enctype' => 'multipart/form-data']) }}

    <!-- Include the CRSF token -->
    {{Form::token()}}

        {{Form::hidden('plantilla_id', (isset($plantilla_id))?$plantilla_id:'' )}}
        {{Form::hidden('torneo_id', (isset($torneo_id))?$torneo_id:'' )}}
    <!-- build our form inputs -->
    <div class="row">


        <div class="form-group col-xs-12 col-sm-6 col-md-3">
            {{Form::label('nombre', 'Nombre')}}
            {{Form::text('nombre', '', ['class' => 'form-control'])}}
        </div>

        <div class="form-group col-xs-12 col-sm-6 col-md-3">
            {{Form::label('apellido', 'Apellido')}}
            {{Form::text('apellido', '', ['class' => 'form-control'])}}
        </div>
        <div class="form-group col-xs-12 col-sm-6 col-md-2">
            {{Form::label('tipoDocumento', 'Tipo')}}
            {{ Form::select('tipoDocumento',['DNI'=>'DNI','PAS'=>'Pasaporte','CI'=>'Cedula'], '',['class' => 'form-control']) }}

        </div>
        <div class="form-group col-xs-12 col-sm-6 col-md-3">
            {{Form::label('documento', 'Documento')}}
            {{Form::text('documento', '', ['class' => 'form-control'])}}
        </div>
    </div>
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-5">
                {{Form::label('email', 'E-mail')}}
                {{Form::email('email', '', ['class' => 'form-control'])}}

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('telefono', 'Teléfono')}}
                {{Form::text('telefono', '', ['class' => 'form-control'])}}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('ciudad', 'Ciudad Nacimiento')}}
                {{Form::text('ciudad', '', ['class' => 'form-control'])}}
            </div>



        </div>
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('nacimiento', 'Nacimiento')}}
                {{Form::date('nacimiento', '', ['class' => 'form-control'])}}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('tipoJugador', 'Posición')}}
                {{ Form::select('tipoJugador',[''=>'Seleccionar...','Arquero'=>'Arquero','Defensor'=>'Defensor','Medio'=>'Medio','Delantero'=>'Delantero'],'', ['class' => 'form-control']) }}

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('pie', 'Pie hábil')}}
                {{ Form::select('pie',[''=>'Seleccionar...','Derecha'=>'Derecha','Izquierda'=>'Izquierda','Ambas'=>'Ambas'],'', ['class' => 'form-control']) }}

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('altura', 'Altura')}}
                {{Form::number('altura', '', ['class' => 'form-control','step' => '0.01'])}}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('peso', 'Peso')}}
                {{Form::number('peso', '', ['class' => 'form-control','step' => '0.01'])}}
            </div>

        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">
                    Foto
                    <input type="file" name="foto" class="form-control" placeholder="">

                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-6">
                {{Form::label('observaciones', 'Observaciones')}}
                {{Form::textarea('observaciones', '', ['class' => 'form-control'])}}

            </div>
        </div>

    <!-- build the submission button -->
    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{ route('jugadores.index')}}" class="btn btn-success">Volver</a>
    {{ Form::close() }}
    </div>
@endsection
