@extends('layouts.app')

@section('pageTitle', 'Nuevo cruce')

@section('content')
    <div class="container">
    <h1 class="display-6">Nuevo cruce @if($torneo) - {{ $torneo->nombre }} {{ $torneo->year }}@endif</h1>

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
    {{ Form::open(['action' => 'CruceController@store', 'enctype' => 'multipart/form-data']) }}

    <!-- Include the CRSF token -->
    {{Form::token()}}


    <!-- build our form inputs -->
    <div class="row">


        <input type="hidden" name="torneo_id" value="{{ $torneo->id ?? '' }}">

        <div class="form-group col-xs-12 col-sm-6 col-md-2">
            {{Form::label('fecha', 'Fecha')}}
            {{Form::date('fecha', '', ['class' => 'form-control'])}}

        </div>
        <div class="form-group col-xs-12 col-sm-6 col-md-2">
            {{Form::label('hora', 'Hora')}}
            {{Form::time('hora', '', ['class' => 'form-control'])}}

        </div>


        <div class="form-group col-xs-12 col-sm-6 col-md-3">
            <label for="fase">Fase</label>
            <input type="text" name="fase" class="form-control" value="{{ old('fase', $cruce->fase ?? '') }}" required>
            <small class="form-text text-muted">Ej: 32avos, 16avos, Octavos, Cuartos, Semifinal, Final</small>
        </div>

        <div class="form-group col-xs-12 col-sm-6 col-md-1">
            {{Form::label('orden', 'Orden')}}
            {{Form::number('orden', '', ['class' => 'form-control'])}}
        </div>

    </div>

        <div class="row">



            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('clasificado_1', 'Clasificado 1')}}
                {{Form::text('clasificado_1', '', ['class' => 'form-control'])}}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('clasificado_2', 'Clasificado 2')}}
                {{Form::text('clasificado_2', '', ['class' => 'form-control'])}}

            </div>



            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                {{Form::label('siguiente_fase', 'Siguiente fase')}}
                {{Form::text('siguiente_fase', '', ['class' => 'form-control'])}}
                <small class="form-text text-muted">Ej: 32avos, 16avos, Octavos, Cuartos, Semifinal, Final</small>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-1">
                {{Form::label('orden_siguiente', 'Sig. orden')}}
                {{Form::number('orden_siguiente', '', ['class' => 'form-control'])}}
            </div>
        </div>

    <!-- build the submission button -->
    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{ route('cruces.index')}}?torneo_id={{ $torneo->id }}" class="btn btn-success">Volver</a>

    {{ Form::close() }}
    </div>
@endsection
