@extends('layouts.app')

@section('pageTitle', 'Nuevo torneo')

@section('content')
    <div class="container">
    <h1 class="display-6">Nuevo torneo</h1>

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
    {{ Form::open(['action' => 'TorneoController@store', 'enctype' => 'multipart/form-data']) }}

    <!-- Include the CRSF token -->
    {{Form::token()}}


    <!-- build our form inputs -->
    <div class="row">
        <div class="form-group col-xs-12 col-sm-6 col-md-6">
            {{Form::label('nombre', 'Nombre')}}
            {{Form::text('nombre', '', ['class' => 'form-control'])}}
        </div>

        <div class="form-group col-xs-12 col-sm-6 col-md-3">
            {{Form::label('year', 'Año')}}
            {{Form::text('year', '', ['class' => 'form-control'])}}
        </div>
    </div>
    <div class="row">
        <div class="form-group col-xs-12 col-sm-6 col-md-2">
            {{Form::label('equipos', 'Nro. de equipos')}}
            {{Form::number('equipos', '', ['class' => 'form-control'])}}
        </div>

        <div class="form-group col-xs-12 col-sm-6 col-md-2">
            {{Form::label('grupos', 'Nro. de grupos')}}
            {{Form::number('grupos', '', ['class' => 'form-control'])}}
        </div>
        <div class="form-group col-xs-12 col-sm-6 col-md-2">
            {{Form::label('tipo', 'Tipo')}}
            {{ Form::select('tipo',[''=>'Seleccionar...','Liga'=>'Liga','Copa'=>'Copa'],'', ['class' => 'form-control']) }}
        </div>
        <div class="form-group col-xs-12 col-sm-6 col-md-3">
            {{Form::label('ambito', 'Ambito')}}
            {{ Form::select('ambito',[''=>'Seleccionar...','Nacional'=>'Nacional','Internacional'=>'Internacional'],'', ['class' => 'form-control']) }}
        </div>
    </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">
                    Escudo
                    <input type="file" name="escudoTmp" class="form-control" placeholder="">

                </div>
            </div>

        </div>
        <div class="row">
            <fieldset>
                <legend>futbol360.com.ar</legend>
                <div class="form-group col-xs-12 col-sm-6 col-md-5">
                    {{Form::label('url_nombre', 'Nombre/s')}}
                    {{Form::text('url_nombre', '', ['class' => 'form-control'])}}
                </div>


            </fieldset>
        </div>
        <div class="form-group col-md-12">
            <h1 class="display-6">Torneos que cuentan para el promedio</h1>

            <table class="table" style="width: 50%">
                <thead>
                <th></th>
                <th>Torneos</th>

                <th><a href="#" class="addRowTorneo"><i class="glyphicon glyphicon-plus"></i></a></th>

                </thead>

                <tbody id="cuerpoTorneo">


                </tbody>




            </table>
        </div>

        <div class="form-group col-md-12">
            <h1 class="display-6">Torneos que cuentan para el acumulado</h1>

            <table class="table" style="width: 50%">
                <thead>
                <th></th>
                <th>Torneos</th>

                <th><a href="#" class="addRowTorneoAcumulado"><i class="glyphicon glyphicon-plus"></i></a></th>

                </thead>

                <tbody id="cuerpoTorneoAcumulado">


                </tbody>




            </table>
        </div>

    <!-- build the submission button -->
    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{route('torneos.index')}}" class="btn btn-success m-1">Volver</a>
    {{ Form::close() }}
    </div>
@endsection
