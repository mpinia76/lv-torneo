@extends('layouts.app')

@section('pageTitle', 'Editar fecha')

@section('content')
    <div class="container">
        <h1 class="display-6">Editar fecha {{$fecha->numero}} del grupo {{$grupo->nombre}} de {{$grupo->torneo->nombre}} {{$grupo->torneo->year}}</h1>

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
    {{ Form::open(['action' => ['FechaController@update', $fecha->id], 'method' => 'put']) }}
    <!-- Include the CRSF token -->
    {{Form::token()}}
    <!-- build our form inputs -->
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{Form::label('numero', 'Número')}}
                {{Form::number('numero', $fecha->numero, ['class' => 'form-control'])}}
                {{Form::hidden('grupo_id', (isset($_GET['grupoId']))?$_GET['grupoId']:$fecha->grupo->id )}}
            </div>
            <div class="form-group col-md-12">
                <h1 class="display-6">Partidos</h1>
                <table class="table">
                    <thead>
                    <th>Fecha</th>
                    <th></th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th></th>
                    <th>Visitante</th>

                    </thead>


                    @foreach($fecha->partidos as $partido)

                        <tr>
                            <td>{{Form::hidden('partido_id[]',$partido->id)}}
                                {{Form::date('fecha[]', ($partido->dia)?date('Y-m-d', strtotime($partido->dia)):'', ['class' => 'form-control'])}}</td>
                            <td> @if($partido->equipol)
                                    @if($partido->equipol->escudo)<img id="original" src="{{ url('images/'.$partido->equipol->escudo) }}" height="20">
                                    @endif
                                @endif</td>
                            <td>
                                {{ Form::select('equipol[]',$equipos, $partido->equipol_id,['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>
                            <td>{{Form::number('golesl[]', $partido->golesl, ['class' => 'form-control', 'size' => '4'])}}</td>
                            <td>{{Form::number('golesv[]', $partido->golesv, ['class' => 'form-control', 'size' => '4'])}}</td>
                            <td> @if($partido->equipov)
                                    @if($partido->equipov->escudo)<img id="original" src="{{ url('images/'.$partido->equipov->escudo) }}" height="20">
                                    @endif
                                @endif</td>

                            <td>{{ Form::select('equipov[]',$equipos, $partido->equipov_id,['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>

        </div>

    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{ route('fechas.index',array('grupoId'=>$grupo->id))}}" class="btn btn-success m-1">Volver</a>
    {{ Form::close() }}
    </div>
@endsection
