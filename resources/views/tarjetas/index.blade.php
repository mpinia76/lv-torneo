@extends('layouts.app')

@section('pageTitle', 'Tarjetas')

@section('content')
    <div class="container">
        <h1 class="display-6">Fecha {{$partido->fecha->numero}} del grupo {{$partido->fecha->grupo->nombre}} de {{$partido->fecha->grupo->torneo->nombre}} {{$partido->fecha->grupo->torneo->year}}</h1>
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
    {{ Form::open(['action' => ['TarjetaController@update', (isset($_GET['partidoId']))?$_GET['partidoId']:''], 'method' => 'put']) }}
    <!-- Include the CRSF token -->
    {{Form::token()}}
        {{Form::hidden('partido_id', (isset($_GET['partidoId']))?$_GET['partidoId']:'' )}}

    <!-- build our form inputs -->
        <div class="row">

            <div class="form-group col-md-12">
                <h1 class="display-6">Partido</h1>
                <table class="table">
                    <thead>
                    <th>Fecha</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>

                    <tr>
                        <td>{{($partido->dia)?date('d/m/Y H:i', strtotime($partido->dia)):''}}</td>
                        <td>@if($partido->equipol)
                                @if($partido->equipol->escudo)<img id="original" src="{{ url('images/'.$partido->equipol->escudo) }}" height="20">
                                @endif
                                {{$partido->equipol->nombre}}
                            @endif
                        </td>
                        <td>{{$partido->golesl}}
                            @if($partido->penalesl)
                                ({{$partido->penalesl}})
                            @endif
                        </td>
                        <td>{{$partido->golesv}}
                            @if($partido->penalesv)
                                ({{$partido->penalesv}})
                            @endif
                        </td>
                        <td>@if($partido->equipov)
                                @if($partido->equipov->escudo)<img id="original" src="{{ url('images/'.$partido->equipov->escudo) }}" height="20">
                                @endif
                                {{$partido->equipov->nombre}}
                            @endif
                        </td>

                    </tr>

                </table>
            </div>

        </div>
        <div class="row">

            <div class="form-group col-md-12">
                <h1 class="display-6">Tarjetas</h1>
                <table class="table" style="width: 60%">
                    <thead>
                    <th></th>
                    <th>Jugador</th>
                    <th>Minuto</th>
                    <th>Tipo</th>
                    <th><a href="#" class="addRowTarjeta"><i class="glyphicon glyphicon-plus"></i></a></th>

                    </thead>

                    <tbody id="cuerpotarjeta">

                    @foreach($tarjetas ?? '' as $tarjeta)

                        <tr>

                            <td>
                                @if($tarjeta->jugador->persona->foto)
                                    <img id="original" class="imgCircle" src="{{ url('images/'.$tarjeta->jugador->persona->foto) }}" >
                                @else
                                    <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                @endif
                                {{Form::hidden('tarjeta_id[]',$tarjeta->id)}}
                            </td>
                            <td>{{ Form::select('jugador[]',$jugadors, $tarjeta->jugador->id,['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>
                            <td>{{Form::number('minuto[]', $tarjeta->minuto, ['class' => 'form-control', 'style' => 'width:70px;'])}}</td>
                            <td>{{ Form::select('tipo[]',['Amarilla'=>'Amarilla','Doble Amarilla'=>'Doble Amarilla','Roja'=>'Roja'], $tarjeta->tipo,['class' => 'form-control']) }}</td>
                            <td><a href="#" class="btn btn-danger removetarjeta"><i class="glyphicon glyphicon-remove"></i></a></td>
                        </tr>
                    @endforeach
                    </tbody>




                </table>

            </div>

        </div>

    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{ route('fechas.show',$partido->fecha->id) }}" class="btn btn-success m-1">Volver</a>
    {{ Form::close() }}
    </div>
@endsection
