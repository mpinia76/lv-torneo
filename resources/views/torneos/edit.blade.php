@extends('layouts.app')

@section('pageTitle', 'Editar torneo')

@section('content')
    <div class="container">
    <h1 class="display-6">Editar torneo</h1>

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
    {{ Form::open(['action' => ['TorneoController@update', $torneo->id], 'method' => 'put', 'enctype' => 'multipart/form-data'F]) }}
    <!-- Include the CRSF token -->
    {{Form::token()}}
    <!-- build our form inputs -->
    <div class="row">
        <div class="form-group col-xs-12 col-sm-6 col-md-6">
            {{Form::label('nombre', 'Nombre')}}
            {{Form::text('nombre', $torneo->nombre, ['class' => 'form-control'])}}
        </div>

        <div class="form-group col-xs-12 col-sm-6 col-md-3">
            {{Form::label('year', 'Año')}}
            {{Form::text('year', $torneo->year, ['class' => 'form-control'])}}
        </div>
    </div>
    <div class="row">
        <div class="form-group col-xs-12 col-sm-6 col-md-2">
            {{Form::label('equipos', 'Nro. de equipos')}}
            {{Form::number('equipos', $torneo->equipos, ['class' => 'form-control'])}}
        </div>

        <div class="form-group col-xs-12 col-sm-6 col-md-2">
            {{Form::label('grupos', 'Nro. de grupos')}}
            {{Form::number('grupos', $torneo->grupos, ['class' => 'form-control'])}}
        </div>
        <div class="form-group col-xs-12 col-sm-6 col-md-2">
            {{Form::label('tipo', 'Tipo')}}
            {{ Form::select('tipo',[''=>'Seleccionar...','Liga'=>'Liga','Copa'=>'Copa'],$torneo->tipo, ['class' => 'form-control']) }}
        </div>
        <div class="form-group col-xs-12 col-sm-6 col-md-3">
            {{Form::label('ambito', 'Ambito')}}
            {{ Form::select('ambito',[''=>'Seleccionar...','Nacional'=>'Nacional','Internacional'=>'Internacional'],$torneo->ambito, ['class' => 'form-control']) }}
        </div>
    </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">
                    Escudo
                    @if($torneo->escudo)
                        <img id="original" src="{{ url('images/'.$torneo->escudo) }}" height="200">
                    @endif
                    <input type="file" name="escudo" class="form-control" placeholder="">

                </div>
            </div>

        </div>
        <div class="row">
            <fieldset>
                <legend>futbol360.com.ar</legend>
                <div class="form-group col-xs-12 col-sm-6 col-md-5">
                    {{Form::label('url_nombre', 'Nombre/s')}}
                    {{Form::text('url_nombre', $torneo->url_nombre, ['class' => 'form-control'])}}
                </div>


            </fieldset>
        </div>
        <div class="form-group col-md-12">
            <h1 class="display-6">Grupos</h1>

            <table class="table" style="width: 50%">
                <thead>
                <th></th>
                <th>Grupos</th>
                <th>Equipos</th>
                <th>Agrupacion</th>
                <th>Posiciones</th>
                <th>Promedios</th>
                <th>Acumulado</th>
                <th>Penales</th>

                <th><a href="#" class="addRowGrupo"><i class="glyphicon glyphicon-plus"></i></a></th>

                </thead>

                <tbody id="cuerpoGrupo">
                @php
                    $i = 0;
                @endphp
                @foreach($grupos ?? '' as $grupo)
                    @php
                        $i++;
                    @endphp
                    <tr>
                        <td>
                            {{Form::hidden('grupo_id[]',$grupo->id)}}
                            {{Form::hidden('items[]',$i)}}
                        </td>
                        <td>{{ Form::text('nombreGrupo[]', $grupo->nombre,['class' => 'form-control', 'style' => 'width: 250px']) }}</td>
                        <td>{{ Form::number('equiposGrupo[]', $grupo->equipos,['class' => 'form-control', 'style' => 'width: 60px']) }}</td>
                        <td>{{ Form::number('agrupacionGrupo[]', $grupo->agrupacion,['class' => 'form-control', 'style' => 'width: 50px']) }}</td>
                        <td>{{ Form::checkbox('posicionesGrupo[]', $i,$grupo->posiciones) }}</td>
                        <td>{{ Form::checkbox('promediosGrupo[]', $i,$grupo->promedios) }}</td>
                        <td>{{ Form::checkbox('acumuladoGrupo[]', $i,$grupo->acumulado) }}</td>
                        <td>{{ Form::checkbox('penalesGrupo[]', $i,$grupo->penales) }}</td>


                        <td><a href="#" class="btn btn-danger removeGrupo"><i class="glyphicon glyphicon-remove"></i></a></td>
                    </tr>
                @endforeach

                <input type="hidden" name="cantGrupos" id="cantGrupos" value="{{$i}}">
                </tbody>




            </table>
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
                @foreach($promedioTorneos ?? '' as $promedioTorneo)

                    <tr>
                        <td>
                            {{Form::hidden('promedioTorneo_id[]',$promedioTorneo->id)}}</td>
                        <td>{{ Form::select('torneoAnterior[]',$torneosAnteriores, $promedioTorneo->torneoAnterior_id,['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>


                        <td><a href="#" class="btn btn-danger removeTorneo"><i class="glyphicon glyphicon-remove"></i></a></td>
                    </tr>
                @endforeach

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
                @foreach($acumuladoTorneos ?? '' as $acumuladoTorneo)

                    <tr>
                        <td>
                            {{Form::hidden('acumuladoTorneo_id[]',$acumuladoTorneo->id)}}</td>
                        <td>{{ Form::select('torneoAnteriorAcumulado[]',$torneosAnteriores, $acumuladoTorneo->torneoAnterior_id,['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>


                        <td><a href="#" class="btn btn-danger removeTorneoAcumulado"><i class="glyphicon glyphicon-remove"></i></a></td>
                    </tr>
                @endforeach

                </tbody>




            </table>
        </div>

    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{route('torneos.index')}}" class="btn btn-success m-1">Volver</a>
    {{ Form::close() }}
    </div>
@endsection
