@extends('layouts.app')

@section('pageTitle', 'Editar tecnico')

@section('content')
    <div class="container">
    <h1 class="display-6">Editar tecnico</h1>

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
    {{ Form::open(['action' => ['TecnicoController@update', $tecnico->id], 'method' => 'put', 'enctype' => 'multipart/form-data']) }}
    <!-- Include the CRSF token -->
    {{Form::token()}}
    <!-- build our form inputs -->
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('nombre', 'Nombre')}}
                {{Form::text('nombre', $tecnico->persona->nombre, ['class' => 'form-control'])}}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('apellido', 'Apellido')}}
                {{Form::text('apellido', $tecnico->persona->apellido, ['class' => 'form-control'])}}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('tipoDocumento', 'Tipo')}}
                {{ Form::select('tipoDocumento',['DNI'=>'DNI','PAS'=>'Pasaporte','CI'=>'Cedula'], $tecnico->persona->tipoDocumento,['class' => 'form-control']) }}

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('documento', 'Documento')}}
                {{Form::text('documento', $tecnico->persona->documento, ['class' => 'form-control'])}}
            </div>

        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('email', 'E-mail')}}
                {{Form::email('email', $tecnico->persona->email, ['class' => 'form-control'])}}

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('telefono', 'Teléfono')}}
                {{Form::text('telefono', $tecnico->persona->telefono, ['class' => 'form-control'])}}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('ciudad', 'Ciudad Nacimiento')}}
                {{Form::text('ciudad', $tecnico->persona->ciudad, ['class' => 'form-control'])}}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('nacimiento', 'Nacimiento')}}
                {{Form::date('nacimiento', ($tecnico->persona->nacimiento)?date('Y-m-d', strtotime($tecnico->persona->nacimiento)):'', ['class' => 'form-control'])}}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('fallecimiento', 'Fallecimiento')}}
                {{Form::date('fallecimiento', ($tecnico->persona->fallecimiento)?date('Y-m-d', strtotime($tecnico->persona->fallecimiento)):'', ['class' => 'form-control'])}}
            </div>
        </div>

        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">
                    Foto
                    @if($tecnico->persona->foto)
                        <img id="original" src="{{ url('images/'.$tecnico->persona->foto) }}" height="200">
                    @endif
                    <input type="file" name="foto" class="form-control" placeholder="">

                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-6">
                {{Form::label('observaciones', 'Observaciones')}}
                {{Form::textarea('observaciones', $tecnico->persona->observaciones, ['class' => 'form-control'])}}

            </div>

        </div>

    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{ route('tecnicos.index')}}" class="btn btn-success">Volver</a>
    {{ Form::close() }}
    </div>
@endsection
