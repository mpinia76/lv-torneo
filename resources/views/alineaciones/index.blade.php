@extends('layouts.app')

@section('pageTitle', 'Alineaciones')

@section('content')
    <div class="container">
        <h1 class="display-6">@if(is_numeric($partido->fecha->numero))
                Fecha {{ $partido->fecha->numero }}
            @else
                {{ $partido->fecha->numero }}@endif del grupo {{$partido->fecha->grupo->nombre}} de {{$partido->fecha->grupo->torneo->nombre}} {{$partido->fecha->grupo->torneo->year}}</h1>
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
    {{ Form::open(['action' => ['AlineacionController@update', (isset($_GET['partidoId']))?$_GET['partidoId']:''], 'method' => 'put']) }}
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
                <h1 class="display-6">
                    @if($partido->equipol)
                        @if($partido->equipol->escudo)<img id="original" src="{{ url('images/'.$partido->equipol->escudo) }}" height="50" >
                        @endif

                    @endif
                    Titulares</h1>
                <table class="table" style="width: 60%">
                    <thead>
                    <th></th>
                    <th>Jugador</th>
                    <th>Dorsal</th>


                    <th><a href="#" class="addRowTitularL"><i class="glyphicon glyphicon-plus"></i></a></th>

                    </thead>

                    <tbody id="cuerpotitularlocal">
                    @php
                        $i = 1;
                    @endphp
                    @foreach($titularesL ?? '' as $titularl)

                        <tr>

                            <td>
                                {{$i++}}
                                @if($titularl->jugador->persona->foto)
                                    <img id="original" class="imgCircle" src="{{ url('images/'.$titularl->jugador->persona->foto) }}" >
                                @else
                                    <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                @endif
                                {{Form::hidden('titularl_id[]',$titularl->id)}}
                            </td>
                            <td>{{ Form::select('titularl[]',$jugadorsL, $titularl->jugador->id,['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>
                            <td>{{Form::number('dorsaltitularl[]', $titularl->dorsal, ['class' => 'form-control', 'style' => 'width:70px;'])}}</td>

                            <td><a href="#" class="btn btn-danger removetitularl"><i class="glyphicon glyphicon-remove"></i></a></td>
                        </tr>
                    @endforeach
                    </tbody>




                </table>
            </div>
            <div class="form-group col-md-12">
                <h1 class="display-6">
                    @if($partido->equipol)
                        @if($partido->equipol->escudo)<img id="original" src="{{ url('images/'.$partido->equipol->escudo) }}" height="50" >
                        @endif

                    @endif
                    Suplentes</h1>
                <table class="table" style="width: 60%">
                    <thead>
                    <th></th>
                    <th>Jugador</th>
                    <th>Dorsal</th>


                    <th><a href="#" class="addRowSuplenteL"><i class="glyphicon glyphicon-plus"></i></a></th>

                    </thead>

                    <tbody id="cuerposuplentelocal">
                    @php
                        $i = 1;
                    @endphp
                    @foreach($suplentesL ?? '' as $suplentel)

                        <tr>

                            <td>
                                {{$i++}}
                                @if($suplentel->jugador->persona->foto)
                                    <img id="original" class="imgCircle" src="{{ url('images/'.$suplentel->jugador->persona->foto) }}" >
                                @else
                                    <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                @endif
                                {{Form::hidden('suplentel_id[]',$suplentel->id)}}
                            </td>
                            <td>{{ Form::select('suplentel[]',$jugadorsL, $suplentel->jugador->id,['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>
                            <td>{{Form::number('dorsalsuplentel[]', $suplentel->dorsal, ['class' => 'form-control', 'style' => 'width:70px;'])}}</td>

                            <td><a href="#" class="btn btn-danger removesuplentel"><i class="glyphicon glyphicon-remove"></i></a></td>
                        </tr>
                    @endforeach
                    </tbody>




                </table>
            </div>
            <div class="form-group col-md-12">
                <h1 class="display-6">
                    @if($partido->equipol)
                        @if($partido->equipol->escudo)<img id="original" src="{{ url('images/'.$partido->equipol->escudo) }}" height="50" >
                        @endif

                    @endif
                    Técnico</h1>
                <a class="btn btn-success m-1" href="{{route('tecnicos.create',  array('partidoId' => $partido->id))}}">Nuevo</a>

                <table class="table" style="width: 50%">
                    <thead>
                    <th></th>
                    <th>Técnico</th>

                    <th><a href="#" class="addRowTecnicoL"><i class="glyphicon glyphicon-plus"></i></a></th>

                    </thead>
                    <tbody id="cuerpoTecnicoL">

                    @foreach($partidoTecnicosL ?? '' as $partidoTecnicoL)

                        <tr>
                            <td>@if($partidoTecnicoL->tecnico->persona->foto)
                                    <img id="original" class="imgCircle" src="{{ url('images/'.$partidoTecnicoL->tecnico->persona->foto) }}" >
                                @else
                                    <img id="original" class="imgCircle" src="{{ url('images/sin_foto_tecnico.png') }}" >
                                @endif
                                {{Form::hidden('partidoTecnicoL_id[]',$partidoTecnicoL->id)}}</td>
                            <td>{{ Form::select('tecnicoL[]',$tecnicos, $partidoTecnicoL->tecnico->id,['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>


                            <td><a href="#" class="btn btn-danger removeTecnicoL"><i class="glyphicon glyphicon-remove"></i></a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="form-group col-md-12">
                <h1 class="display-6">
                    @if($partido->equipov)
                        @if($partido->equipov->escudo)<img id="original" src="{{ url('images/'.$partido->equipov->escudo) }}" height="50" >
                        @endif

                    @endif
                    Titulares</h1>
                <table class="table" style="width: 60%">
                    <thead>
                    <th></th>
                    <th>Jugador</th>
                    <th>Dorsal</th>


                    <th><a href="#" class="addRowTitularV"><i class="glyphicon glyphicon-plus"></i></a></th>

                    </thead>

                    <tbody id="cuerpotitularvisitante">
                    @php
                        $i = 1;
                    @endphp
                    @foreach($titularesV ?? '' as $titularv)

                        <tr>

                            <td>
                                {{$i++}}
                                @if($titularv->jugador->persona->foto)
                                    <img id="original" class="imgCircle" src="{{ url('images/'.$titularv->jugador->persona->foto) }}" >
                                @else
                                    <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                @endif
                                {{Form::hidden('titularv_id[]',$titularv->id)}}
                            </td>
                            <td>{{ Form::select('titularv[]',$jugadorsV, $titularv->jugador->id,['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>
                            <td>{{Form::number('dorsaltitularv[]', $titularv->dorsal, ['class' => 'form-control', 'style' => 'width:70px;'])}}</td>

                            <td><a href="#" class="btn btn-danger removetitularv"><i class="glyphicon glyphicon-remove"></i></a></td>
                        </tr>
                    @endforeach
                    </tbody>




                </table>
            </div>
            <div class="form-group col-md-12">
                <h1 class="display-6">
                    @if($partido->equipov)
                        @if($partido->equipov->escudo)<img id="original" src="{{ url('images/'.$partido->equipov->escudo) }}" height="50" >
                        @endif

                    @endif
                    Suplentes</h1>
                <table class="table" style="width: 60%">
                    <thead>
                    <th></th>
                    <th>Jugador</th>
                    <th>Dorsal</th>


                    <th><a href="#" class="addRowSuplenteV"><i class="glyphicon glyphicon-plus"></i></a></th>

                    </thead>

                    <tbody id="cuerposuplentevisitante">
                    @php
                        $i = 1;
                    @endphp
                    @foreach($suplentesV ?? '' as $suplentev)

                        <tr>

                            <td>
                                {{$i++}}
                                @if($suplentev->jugador->persona->foto)
                                    <img id="original" class="imgCircle" src="{{ url('images/'.$suplentev->jugador->persona->foto) }}" >
                                @else
                                    <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                @endif
                                {{Form::hidden('suplentev_id[]',$suplentev->id)}}
                            </td>
                            <td>{{ Form::select('suplentev[]',$jugadorsV, $suplentev->jugador->id,['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>
                            <td>{{Form::number('dorsalsuplentev[]', $suplentev->dorsal, ['class' => 'form-control', 'style' => 'width:70px;'])}}</td>

                            <td><a href="#" class="btn btn-danger removesuplentev"><i class="glyphicon glyphicon-remove"></i></a></td>
                        </tr>
                    @endforeach
                    </tbody>




                </table>
            </div>
            <div class="form-group col-md-12">
                <h1 class="display-6">
                    @if($partido->equipov)
                        @if($partido->equipov->escudo)<img id="original" src="{{ url('images/'.$partido->equipov->escudo) }}" height="50" >
                        @endif

                    @endif
                    Técnico</h1>
                <a class="btn btn-success m-1" href="{{route('tecnicos.create',  array('partidoId' => $partido->id))}}">Nuevo</a>

                <table class="table" style="width: 50%">
                    <thead>
                    <th></th>
                    <th>Técnico</th>

                    <th><a href="#" class="addRowTecnicoV"><i class="glyphicon glyphicon-plus"></i></a></th>

                    </thead>
                    <tbody id="cuerpoTecnicoV">

                    @foreach($partidoTecnicosV ?? '' as $partidoTecnicoV)

                        <tr>
                            <td>@if($partidoTecnicoV->tecnico->persona->foto)
                                    <img id="original" class="imgCircle" src="{{ url('images/'.$partidoTecnicoV->tecnico->persona->foto) }}" >
                                @else
                                    <img id="original" class="imgCircle" src="{{ url('images/sin_foto_tecnico.png') }}" >
                                @endif
                                {{Form::hidden('partidoTecnicoV_id[]',$partidoTecnicoV->id)}}</td>
                            <td>{{ Form::select('tecnicoV[]',$tecnicos, $partidoTecnicoV->tecnico->id,['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>


                            <td><a href="#" class="btn btn-danger removeTecnicoV"><i class="glyphicon glyphicon-remove"></i></a></td>
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
