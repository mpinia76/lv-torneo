@extends('layouts.app')

@section('pageTitle', 'Editar arbitro')

@section('content')
    <div class="container">
    <h1 class="display-6">Editar arbitro</h1>

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
    {{ Form::open(['action' => ['ArbitroController@update', $arbitro->id], 'method' => 'put', 'enctype' => 'multipart/form-data']) }}
    <!-- Include the CRSF token -->
    {{Form::token()}}
    <!-- build our form inputs -->
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('nombre', 'Nombre')}}
                {{Form::text('nombre', $arbitro->persona->nombre, ['class' => 'form-control'])}}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('apellido', 'Apellido')}}
                {{Form::text('apellido', $arbitro->persona->apellido, ['class' => 'form-control'])}}
            </div>
            <!--<div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('email', 'E-mail')}}
                {{Form::email('email', $arbitro->persona->email, ['class' => 'form-control'])}}

            </div>-->
            <div class="form-group col-xs-12 col-sm-6 col-md-5">
                {{Form::label('name', 'Mostrar')}}
                {{Form::text('name', $arbitro->persona->name, ['class' => 'form-control'])}}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{ Form::label('verificado', 'Verificado', ['class' => 'control-label']) }}
                <div class="checkbox">
                    <label>
                        {{ Form::checkbox('verificado', 1, $arbitro->persona->verificado) }}
                    </label>
                </div>
            </div>
        </div>
        <div class="row">


            <!--<div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('telefono', 'Teléfono')}}
                {{Form::text('telefono', $arbitro->persona->telefono, ['class' => 'form-control'])}}
            </div>-->
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('nacionalidad', 'Nacionalidad')}}
                {{Form::text('nacionalidad', $arbitro->persona->nacionalidad, ['class' => 'form-control'])}}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('ciudad', 'Ciudad Nacimiento')}}
                {{Form::text('ciudad', $arbitro->persona->ciudad, ['class' => 'form-control'])}}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('nacimiento', 'Nacimiento')}}
                {{Form::date('nacimiento', ($arbitro->persona->nacimiento)?date('Y-m-d', strtotime($arbitro->persona->nacimiento)):'', ['class' => 'form-control'])}}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('fallecimiento', 'Fallecimiento')}}
                {{Form::date('fallecimiento', ($arbitro->persona->fallecimiento)?date('Y-m-d', strtotime($arbitro->persona->fallecimiento)):'', ['class' => 'form-control'])}}
            </div>
        </div>

        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">
                    Foto
                    @if($arbitro->persona->foto)
                        <img id="original" src="{{ url('images/'.$arbitro->persona->foto) }}" height="200">
                    @endif
                    <input type="file" name="foto" class="form-control" placeholder="">

                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-6">
                {{Form::label('observaciones', 'Observaciones')}}
                {{Form::textarea('observaciones', $arbitro->persona->observaciones, ['class' => 'form-control'])}}

            </div>

        </div>

    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{ route('arbitros.index')}}" class="btn btn-success">Volver</a>
    {{ Form::close() }}
    </div>
@endsection
