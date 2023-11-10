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

                <input type="hidden" name="posicionEquipo" id="posicionEquipo" value="<?php echo count($arrPosiciones); ?>">
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
                    @foreach($arrPosiciones as $key => $value)

                        <tr>
                            <td>
                                {{$i++}}
                                @if($value[1])
                                    <img id="original" src="{{ url('images/'.$value[1]) }}" height="25">
                                @endif
                                {{Form::hidden('posicion[]',($key+1))}}
                            </td>

                            <td>
                                {{ Form::select('equipo[]',$equipos, $value[0],['id'=>'equipo'.$i,'class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>


                            <td><a href="#" class="btn btn-danger removePosicionEquipo"><i class="glyphicon glyphicon-remove"></i></a></td>
                        </tr>

                    @endforeach
                    </tbody>
                    <thead>
                    <th></th>
                    <th>Equipo</th>

                    <th><a href="#" class="addRowPosicion"><i class="glyphicon glyphicon-plus"></i></a></th>

                    </thead>
                </table>

            </div>

        </div>

        {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}

        <a href="{{ route('torneos.show',$torneo->id) }}" class="btn btn-success m-1">Volver</a>
    {{ Form::close() }}


@endsection
