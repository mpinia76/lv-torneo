@extends('layouts.app')

@section('pageTitle', 'Editar fecha')

@section('content')
    <div class="container">
        <h1 class="display-6">Editar @if(is_numeric($fecha->numero))
                Fecha {{ $fecha->numero }}
            @else
                {{ $fecha->numero }} @endif del grupo {{$grupo->nombre}} de {{$grupo->torneo->nombre}} {{$grupo->torneo->year}}</h1>

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
                {{Form::text('numero', $fecha->numero, ['class' => 'form-control'])}}
                {{Form::hidden('grupo_id', (isset($_GET['grupoId']))?$_GET['grupoId']:$fecha->grupo->id )}}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                {{ Form::label('orden_fecha', 'Orden') }}
                {{ Form::number('orden_fecha', $fecha->orden, ['class' => 'form-control']) }}
            </div>
            <div class="form-group col-md-12">
                <h1 class="display-6">Partidos</h1>
                <table class="table">
                    <thead>
                    <th>Bloquear</th>
                    <th>Orden</th>
                    <th >Fecha</th>
                    <th >Hora</th>
                    <th>Neutral</th>
                    <th></th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th></th>
                    <th>Visitante</th>
                    @if($grupo->penales)
                        <th>PL</th>
                        <th>PV</th>
                    @endif
                    <th><a href="#" class="addRowFecha"><i class="glyphicon glyphicon-plus"></i></a></th>
                    </thead>

                    <tbody id="cuerpoFecha">
                    @php
                        $i = 0;
                    @endphp
                    @foreach($fecha->partidos as $partido)
                        @php
                            $i++;
                        @endphp
                        <tr>
                            <td>{{ Form::checkbox('bloquear[]', 1,($partido->bloquear)?true:false) }}</td>
                            <td>{{Form::number('orden[]', $partido->orden, ['class' => 'form-control', 'style' => 'width: 50px'])}}</td>
                            <td>{{Form::hidden('partido_id[]',$partido->id)}}
                                {{Form::date('fecha[]', ($partido->dia)?date('Y-m-d', strtotime($partido->dia)):'', ['class' => 'form-control','style' =>'width:155px;'])}}
                            </td>
                            <td>
                                {{Form::time('hora[]', ($partido->dia)?date('H:i', strtotime($partido->dia)):'', ['class' => 'form-control'])}}

                            </td>
                            <td>{{ Form::checkbox('neutral[]', 1,($partido->neutral)?true:false) }}</td>
                            <td> @if($partido->equipol)
                                    @if($partido->equipol->escudo)<img id="original" src="{{ url('images/'.$partido->equipol->escudo) }}" height="20">
                                    @endif
                                @endif</td>
                            <td>
                                {{ Form::select('equipol[]',$equipos, $partido->equipol_id,['class' => 'form-control js-example-basic-single', 'style' => 'width: 250px']) }}</td>
                            <td>{{Form::number('golesl[]', $partido->golesl, ['class' => 'form-control', 'style' => 'width: 50px'])}}</td>
                            <td>{{Form::number('golesv[]', $partido->golesv, ['class' => 'form-control', 'style' => 'width: 50px'])}}</td>

                            <td> @if($partido->equipov)
                                    @if($partido->equipov->escudo)<img id="original" src="{{ url('images/'.$partido->equipov->escudo) }}" height="20">
                                    @endif
                                @endif</td>

                            <td>{{ Form::select('equipov[]',$equipos, $partido->equipov_id,['class' => 'form-control js-example-basic-single', 'style' => 'width: 250px']) }}</td>
                            @if($grupo->penales)
                                <td>{{Form::number('penalesl[]', $partido->penalesl, ['class' => 'form-control', 'style' => 'width: 50px'])}}</td>
                                <td>{{Form::number('penalesv[]', $partido->penalesv, ['class' => 'form-control', 'style' => 'width: 50px'])}}</td>
                            @endif
                            <td><a href="#" class="btn btn-danger removefecha"><i class="glyphicon glyphicon-remove"></i></a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

        </div>
        <div class="row">
            <fieldset>
                <legend>futbol360.com.ar</legend>
                <div class="form-group col-xs-12 col-sm-6 col-md-5">
                    {{Form::label('url_nombre', 'Nombre/s')}}
                    {{Form::text('url_nombre', $fecha->url_nombre, ['class' => 'form-control'])}}
                </div>


            </fieldset>
        </div>
    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{ route('fechas.index',array('grupoId'=>$grupo->id))}}" class="btn btn-success m-1">Volver</a>
    {{ Form::close() }}
    </div>
@endsection
