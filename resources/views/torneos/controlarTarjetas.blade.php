@extends('layouts.app')

@section('pageTitle', 'Controlar tarjetas')
<style>
    /* Estilos personalizados para resaltar la pestaña activa */
    .nav-link.active {
        background-color: #007bff; /* Cambia el color de fondo de la pestaña activa */
        color: #fff; /* Cambia el color del texto de la pestaña activa */
        border-color: #007bff; /* Cambia el color del borde de la pestaña activa */
    }

    /* Agrega un espacio entre las pestañas y el contenido */
    .tab-content {
        margin: 20px; /* Ajusta el margen superior del contenido */
    }
</style>
@section('content')
    <div class="container">
        <h1 class="display-6">Controlar tarjetas</h1>

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
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="sinjugar-tab" data-toggle="tab" href="#sinjugar" role="tab" aria-controls="sinjugar" aria-selected="true">Sin jugar</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="repetidos-tab" data-toggle="tab" href="#repetidos" role="tab" aria-controls="repetidos" aria-selected="false">Repetidas</a>
            </li>



        </ul>
        <div class="tab-content" id="myTabContent">
            <div role="tabpanel" class="tab-pane active" id="sinjugar">

        <!-- build our form inputs -->
        <div class="row">

            <div class="form-group col-md-12">

                <table class="table" style="font-size: 14px;">
                    <thead>
                    <th>Jugador</th>
                    <th>Torneo</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>


                    @foreach($partidos as $partido)

                        @if($partido->dia)
                            <tr>
                                <td>@if($partido->jugador_foto)
                                    <img id="original" class="imgCircle" src="{{ url('images/'.$partido->jugador_foto) }}" >
                                @else
                                    <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                    @endif{{$partido->jugador_apellido}}, {{$partido->jugador_nombre}}</td>
                                <td>{{$partido->torneo}} {{$partido->year}}</td>
                                <td>{{$partido->fecha}}</td>
                                <td>{{($partido->dia)?date('d/m/Y H:i', strtotime($partido->dia)):''}}</td>
                                <td>@if($partido->equipo_local_nombre)
                                        @if($partido->equipo_local_escudo)<img id="original" src="{{ url('images/'.$partido->equipo_local_escudo) }}" height="20">
                                        @endif
                                        {{$partido->equipo_local_nombre}}
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
                                <td>@if($partido->equipo_visitante_nombre)
                                        @if($partido->equipo_visitante_escudo)<img id="original" src="{{ url('images/'.$partido->equipo_visitante_escudo) }}" height="20">
                                        @endif
                                        {{$partido->equipo_visitante_nombre}}
                                    @endif
                                </td>

                                <td>
                                    <div class="d-flex">

                                        <a href="{{route('alineaciones.index', array('partidoId' => $partido->id))}}" class="btn btn-success m-1">Alineaciones</a>
                                        <a href="{{route('tarjetas.index', array('partidoId' => $partido->id))}}" class="btn btn-info m-1">Tarjetas</a>

                                    </div>

                                </td>
                            </tr>
                        @endif
                    @endforeach
                </table>
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-9">
                        {{ $partidos->links() }}
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-2">
                        <strong>Total: {{ $partidos->total() }}</strong>
                    </div>
                </div>
            </div>

        </div>

            </div>
            <div role="tabpanel" class="tab-pane" id="repetidos">

        <!-- build our form inputs -->
        <div class="row">

            <div class="form-group col-md-12">

                <table class="table" style="font-size: 14px;">
                    <thead>
                    <th>Jugador</th>
                    <th>Torneo</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>


                    @foreach($tarjetas as $tarjeta)

                        @if($tarjeta->dia)
                            <tr>
                                <td>@if($tarjeta->jugador_foto)
                                        <img id="original" class="imgCircle" src="{{ url('images/'.$tarjeta->jugador_foto) }}" >
                                    @else
                                        <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                    @endif{{$tarjeta->jugador_apellido}}, {{$tarjeta->jugador_nombre}}</td>
                                <td>{{$tarjeta->torneo}} {{$tarjeta->year}}</td>
                                <td>{{$tarjeta->fecha}}</td>
                                <td>{{($tarjeta->dia)?date('d/m/Y H:i', strtotime($tarjeta->dia)):''}}</td>
                                <td>@if($tarjeta->equipo_local_nombre)
                                        @if($tarjeta->equipo_local_escudo)<img id="original" src="{{ url('images/'.$tarjeta->equipo_local_escudo) }}" height="20">
                                        @endif
                                        {{$tarjeta->equipo_local_nombre}}
                                    @endif
                                </td>
                                <td>{{$tarjeta->golesl}}
                                    @if($tarjeta->penalesl)
                                        ({{$tarjeta->penalesl}})
                                    @endif
                                </td>
                                <td>{{$tarjeta->golesv}}
                                    @if($tarjeta->penalesv)
                                        ({{$tarjeta->penalesv}})
                                    @endif
                                </td>
                                <td>@if($tarjeta->equipo_visitante_nombre)
                                        @if($tarjeta->equipo_visitante_escudo)<img id="original" src="{{ url('images/'.$tarjeta->equipo_visitante_escudo) }}" height="20">
                                        @endif
                                        {{$tarjeta->equipo_visitante_nombre}}
                                    @endif
                                </td>

                                <td>
                                    <div class="d-flex">


                                        <a href="{{route('tarjetas.index', array('partidoId' => $tarjeta->id))}}" class="btn btn-info m-1">Tarjetas</a>

                                    </div>

                                </td>
                            </tr>
                        @endif
                    @endforeach
                </table>
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-9">
                        {{ $tarjetas->links() }}
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-2">
                        <strong>Total: {{ $tarjetas->total() }}</strong>
                    </div>
                </div>
            </div>

        </div>
            </div>
        </div>

        <a href="{{ route('torneos.index') }}" class="btn btn-success m-1">Volver</a>

    </div>
@endsection
