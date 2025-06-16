@extends('layouts.app')

@section('pageTitle', 'Nueva fecha')

@section('content')
    <div class="container">
    <h1 class="display-6">Nueva fecha del grupo {{$grupo->nombre}} de {{$grupo->torneo->nombre}} {{$grupo->torneo->year}}</h1>

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
    {{ Form::open(['action' => 'FechaController@store']) }}

    <!-- Include the CRSF token -->
    {{Form::token()}}


    <!-- build our form inputs -->
    <div class="row">
        <div class="form-group col-xs-12 col-sm-6 col-md-2">
            {{Form::label('numero', 'Número')}}
            {{Form::text('numero', '', ['class' => 'form-control'])}}
            {{Form::hidden('grupo_id', (isset($_GET['grupoId']))?$_GET['grupoId']:'' )}}
        </div>
        <div class="form-group col-md-12">
        <h1 class="display-6">Partidos</h1>
        <table class="table">
            <thead>
            <th>Fecha</th>
            <th >Hora</th>
            <th >Neutral</th>
            <th>Local</th>
            <th>GL</th>
            <th>GV</th>
            <th>Visitante</th>
            @if($grupo->penales)
                <th>PL</th>
                <th>PV</th>
            @endif

            </thead>


            @for ($i =1; $i <= $grupo->equipos/2; $i++)

                <tr>
                    <td>{{Form::date('fecha[]', '', ['class' => 'form-control','style' =>'width:155px;'])}}</td>
                    <td>{{Form::time('hora[]', '', ['class' => 'form-control'])}}</td>
                    <td>{{ Form::checkbox('neutral[]', $i,false) }}</td>
                    <td>{{ Form::select('equipol[]',$equipos, '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 250px']) }}</td>
                    <td>{{Form::number('golesl[]', '', ['class' => 'form-control', 'style' => 'width: 50px'])}}</td>
                    <td>{{Form::number('golesv[]', '', ['class' => 'form-control', 'style' => 'width: 50px'])}}</td>
                    <td>{{ Form::select('equipov[]',$equipos, '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 250px']) }}</td>

                    @if($grupo->penales)
                        <td>{{Form::number('penalesl[]', '', ['class' => 'form-control', 'style' => 'width: 50px'])}}</td>
                        <td>{{Form::number('penalesv[]', '', ['class' => 'form-control', 'style' => 'width: 50px'])}}</td>
                    @endif
                </tr>
            @endfor
        </table>
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
    <!-- build the submission button -->
    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{route('fechas.index',array('grupoId'=>$grupo->id)) }}" class="btn btn-success m-1">Volver</a>
    {{ Form::close() }}
    </div>


@endsection

