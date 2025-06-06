@extends('layouts.app')

@section('pageTitle', 'Editar jugador')

@section('content')
    <div class="container">
    <h1 class="display-6">Editar jugador</h1>

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
    {{ Form::open(['action' => ['JugadorController@update', $jugador->id], 'method' => 'put', 'enctype' => 'multipart/form-data']) }}
    <!-- Include the CRSF token -->
    {{Form::token()}}
    <!-- build our form inputs -->
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('nombre', 'Nombre')}}
                {{Form::text('nombre', $jugador->persona->nombre, ['class' => 'form-control'])}}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('apellido', 'Apellido')}}
                {{Form::text('apellido', $jugador->persona->apellido, ['class' => 'form-control'])}}
            </div>
            <!--<div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('tipoDocumento', 'Tipo')}}
                {{ Form::select('tipoDocumento',['DNI'=>'DNI','PAS'=>'Pasaporte','CI'=>'Cedula'], $jugador->persona->tipoDocumento,['class' => 'form-control']) }}

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('documento', 'Documento')}}
                {{Form::text('documento', $jugador->persona->documento, ['class' => 'form-control'])}}
            </div>-->
            <div class="form-group col-xs-12 col-sm-6 col-md-5">
                {{Form::label('name', 'Mostrar')}}
                {{Form::text('name', $jugador->persona->name, ['class' => 'form-control'])}}
            </div>

        </div>
        <div class="row">

            <!--<div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('email', 'E-mail')}}
                {{Form::email('email', $jugador->persona->email, ['class' => 'form-control'])}}

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('telefono', 'Teléfono')}}
                {{Form::text('telefono', $jugador->persona->telefono, ['class' => 'form-control'])}}
            </div>-->
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('nacionalidad', 'Nacionalidad')}}
                {{Form::text('nacionalidad', $jugador->persona->nacionalidad, ['class' => 'form-control'])}}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('ciudad', 'Ciudad Nacimiento')}}
                {{Form::text('ciudad', $jugador->persona->ciudad, ['class' => 'form-control'])}}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('nacimiento', 'Nacimiento')}}
                {{Form::date('nacimiento', ($jugador->persona->nacimiento)?date('Y-m-d', strtotime($jugador->persona->nacimiento)):'', ['class' => 'form-control'])}}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('fallecimiento', 'Fallecimiento')}}
                {{Form::date('fallecimiento', ($jugador->persona->fallecimiento)?date('Y-m-d', strtotime($jugador->persona->fallecimiento)):'', ['class' => 'form-control'])}}
            </div>


        </div>
        <div class="row">


            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('tipoJugador', 'Posición')}}
                {{ Form::select('tipoJugador',[''=>'Seleccionar...','Arquero'=>'Arquero','Defensor'=>'Defensor','Medio'=>'Medio','Delantero'=>'Delantero'],$jugador->tipoJugador, ['class' => 'form-control']) }}

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('pie', 'Pie hábil')}}
                {{ Form::select('pie',[''=>'Seleccionar...','Derecha'=>'Derecha','Izquierda'=>'Izquierda','Ambas'=>'Ambas'],$jugador->pie, ['class' => 'form-control']) }}

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('altura', 'Altura')}}
                {{Form::number('altura', $jugador->persona->altura, ['class' => 'form-control','step' => '0.01'])}}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('peso', 'Peso')}}
                {{Form::number('peso', $jugador->persona->peso, ['class' => 'form-control','step' => '0.01'])}}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{ Form::label('verificado', 'Verificado', ['class' => 'control-label']) }}
                <div class="checkbox">
                    <label>
                        {{ Form::checkbox('verificado', 1, $jugador->persona->verificado) }}
                    </label>
                </div>
            </div>
        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">
                    Foto
                    @if($jugador->persona->foto)
                        <img id="original" src="{{ url('images/'.$jugador->persona->foto) }}" height="200">
                    @endif
                    <input type="file" name="foto" class="form-control" placeholder="">

                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-6">
                {{Form::label('observaciones', 'Observaciones')}}
                {{Form::textarea('observaciones', $jugador->persona->observaciones, ['class' => 'form-control'])}}

            </div>

        </div>
        <div class="row">
            <fieldset>
                <legend>futbol360.com.ar</legend>
                <div class="form-group col-xs-12 col-sm-6 col-md-5">
                    {{Form::label('url_nombre', 'Nombre')}}
                    {{Form::text('url_nombre', $jugador->url_nombre, ['class' => 'form-control'])}}
                </div>


            </fieldset>
        </div>
    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{ route('jugadores.index')}}" class="btn btn-success">Volver</a>
    {{ Form::close() }}
    </div>
@endsection
