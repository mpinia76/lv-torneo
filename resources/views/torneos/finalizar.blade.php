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
        @if($torneo->parcial)
            <div class="alert alert-danger">
                <h5 class="mb-2">Ojo: este torneo está marcado como <b>parcial</b></h5>
                <p class="mb-2">Los torneos parciales los creó el importador de partidos DT por DT, así que
                    tienen <b>solo los partidos del equipo que dirigía ese técnico</b> — no los del resto.
                    Una tabla de posiciones armada sobre eso no es real.</p>
                <p class="mb-2">Y la posición 1 que guardes acá se propaga como <b>título</b> a las fichas del
                    equipo, del técnico y de sus jugadores, y al ranking de títulos.</p>
                <p class="mb-0">Si aun así sabés cuál fue la posición —por ejemplo una copa donde el partido
                    de la final sí está cargado— marcá la casilla de abajo para habilitar el guardado.</p>
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

        @if($torneo->parcial)
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="confirmo_parcial" value="1"
                       id="confirmoParcial" onclick="document.getElementById('btnGuardarPosiciones').disabled = !this.checked;">
                <label class="form-check-label" for="confirmoParcial">
                    Sé que el torneo está parcial y quiero guardar estas posiciones igual.
                </label>
            </div>
            {{Form::submit('Guardar', ['class' => 'btn btn-primary', 'id' => 'btnGuardarPosiciones', 'disabled' => 'disabled'])}}
        @else
            {{Form::submit('Guardar', ['class' => 'btn btn-primary', 'id' => 'btnGuardarPosiciones'])}}
        @endif

        <a href="{{ route('torneos.show',$torneo->id) }}" class="btn btn-success m-1">Volver</a>
    {{ Form::close() }}


@endsection
