@extends('layouts.app')

@section('pageTitle', 'Guardar Posiciones')

@section('content')
    <div class="container">
        <h1 class="display-6">Posiciones finales del {{$torneo->nombre}} {{$torneo->year}}</h1>

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
        @if (\Session::has('error'))
            <div class="alert alert-danger">
                <ul>
                    <li>{!! \Session::get('error') !!}</li>
                </ul>
            </div>
        @endif
        @if (\Session::has('success'))
            <div class="alert alert-success">
                <ul>
                    <li>{!! \Session::get('success') !!}</li>
                </ul>
            </div>
        @endif
        <!-- Open the form with the store function route. -->
        {{ Form::open(['action' => ['TorneoController@guardarFinalizar'], 'method' => 'put']) }}
        <!-- Include the CRSF token -->
        {{Form::token()}}
        <!-- build our form inputs -->
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-2">

                {{Form::hidden('torneo_id', (isset($_GET['torneoId']))?$_GET['torneoId']:$torneo->id )}}

            </div>

            <div class="form-group col-md-12">
                <h1 class="display-6">Posiciones</h1>


                <table class="table" style="width: 50%">
                    <thead>
                    <th></th>
                    <th>Equipo</th>

                    <th><a href="#" class="addRowPosicion"><i class="glyphicon glyphicon-plus"></i></a></th>

                    </thead>
                    <tbody id="cuerpoPosicion">
                    @php
                        $i = 1;
                    @endphp
                    @foreach($plantillaJugadors ?? '' as $plantillaJugador)

                        <tr>
                            <td>
                                {{$i++}}
                                @if($plantillaJugador->jugador->persona->foto)
                                    <img id="original" class="imgCircle" src="{{ url('images/'.$plantillaJugador->jugador->persona->foto) }}" >
                                @else
                                    <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                @endif
                                {{Form::hidden('plantillajugador_id[]',$plantillaJugador->id)}}
                            </td>

                            <td>{{ Form::select('jugador[]',$jugadors, $plantillaJugador->jugador->id,['id'=>'jugador'.$i,'class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>
                            <td>{{Form::number('dorsal[]', $plantillaJugador->dorsal, ['class' => 'form-control', 'style' => 'width:70px;'])}}</td>

                            <td><a href="#" class="btn btn-danger remove"><i class="glyphicon glyphicon-remove"></i></a></td>
                        </tr>

                    @endforeach
                    </tbody>
                    <thead>
                    <th></th>
                    <th>Jugador</th>
                    <th>Dorsal</th>
                    <th><a href="#" class="addRow"><i class="glyphicon glyphicon-plus"></i></a></th>

                    </thead>
                </table>
                <a class="btn btn-success m-1" href="{{route('jugadores.create',  array('plantillaId' => $plantilla->id))}}">Nuevo</a>
            </div>

        </div>

        {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}

        <a href="{{ route('plantillas.index',array('grupoId'=>$grupo->id))  }}" class="btn btn-success m-1">Volver</a>
    {{ Form::close() }}


@endsection
