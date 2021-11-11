@extends('layouts.app')

@section('pageTitle', 'Nuevo arbitro')

@section('content')
    <div class="container">
    <h1 class="display-6">Nuevo arbitro</h1>

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
    {{ Form::open(['action' => 'ArbitroController@store', 'enctype' => 'multipart/form-data']) }}

    <!-- Include the CRSF token -->
    {{Form::token()}}

        {{Form::hidden('partido_id', (isset($_GET['partidoId']))?$_GET['partidoId']:'' )}}
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
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('email', 'E-mail')}}
                {{Form::email('email', '', ['class' => 'form-control'])}}

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('telefono', 'Teléfono')}}
                {{Form::text('telefono', '', ['class' => 'form-control'])}}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('ciudad', 'Ciudad Nacimiento')}}
                {{Form::text('ciudad', '', ['class' => 'form-control'])}}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('nacimiento', 'Nacimiento')}}
                {{Form::date('nacimiento', '', ['class' => 'form-control'])}}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('fallecimiento', 'Fallecimiento')}}
                {{Form::date('fallecimiento', '', ['class' => 'form-control'])}}
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
        <a href="{{ route('arbitros.index')}}" class="btn btn-success">Volver</a>
    {{ Form::close() }}
    </div>
@endsection
