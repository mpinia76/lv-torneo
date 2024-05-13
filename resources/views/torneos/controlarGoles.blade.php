@extends('layouts.app')

@section('pageTitle', 'Controlar goles')
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
        <h1 class="display-6">Controlar goles</h1>

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
                <a class="nav-link" id="repetidos-tab" data-toggle="tab" href="#repetidos" role="tab" aria-controls="repetidos" aria-selected="false">Repetidos</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="diferencia-tab" data-toggle="tab" href="#diferencia" role="tab" aria-controls="diferencia" aria-selected="false">Diferencia</a>
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
                                        <a href="{{route('goles.index', array('partidoId' => $partido->id, 'totalGoles' => $partido->golesl+$partido->golesv))}}" class="btn btn-info m-1">Goles</a>
                                        <a href="{{route('cambios.index', array('partidoId' => $partido->id))}}" class="btn btn-primary m-1">Sustituciones</a>
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


                    @foreach($gols as $gol)

                        @if($gol->dia)
                            <tr>
                                <td>@if($gol->jugador_foto)
                                        <img id="original" class="imgCircle" src="{{ url('images/'.$gol->jugador_foto) }}" >
                                    @else
                                        <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                    @endif{{$gol->jugador_apellido}}, {{$gol->jugador_nombre}}</td>
                                <td>{{$gol->torneo}} {{$gol->year}}</td>
                                <td>{{$gol->fecha}}</td>
                                <td>{{($gol->dia)?date('d/m/Y H:i', strtotime($gol->dia)):''}}</td>
                                <td>@if($gol->equipo_local_nombre)
                                        @if($gol->equipo_local_escudo)<img id="original" src="{{ url('images/'.$gol->equipo_local_escudo) }}" height="20">
                                        @endif
                                        {{$gol->equipo_local_nombre}}
                                    @endif
                                </td>
                                <td>{{$gol->golesl}}
                                    @if($gol->penalesl)
                                        ({{$gol->penalesl}})
                                    @endif
                                </td>
                                <td>{{$gol->golesv}}
                                    @if($gol->penalesv)
                                        ({{$gol->penalesv}})
                                    @endif
                                </td>
                                <td>@if($gol->equipo_visitante_nombre)
                                        @if($gol->equipo_visitante_escudo)<img id="original" src="{{ url('images/'.$gol->equipo_visitante_escudo) }}" height="20">
                                        @endif
                                        {{$gol->equipo_visitante_nombre}}
                                    @endif
                                </td>

                                <td>
                                    <div class="d-flex">


                                        <a href="{{route('goles.index', array('partidoId' => $gol->id, 'totalGoles' => $gol->golesl+$gol->golesv))}}" class="btn btn-info m-1">Goles</a>

                                    </div>

                                </td>
                            </tr>
                        @endif
                    @endforeach
                </table>
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-9">
                        {{ $gols->links() }}
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-2">
                        <strong>Total: {{ $gols->total() }}</strong>
                    </div>
                </div>
            </div>

        </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="diferencia">

        <!-- build our form inputs -->
        <div class="row">

            <div class="form-group col-md-12">

                <table class="table" style="font-size: 14px;">
                    <thead>

                    <th>Torneo</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>


                    @foreach($diferencia as $partido)

                        @if($partido->dia)
                            <tr>
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

                                        <a href="{{route('goles.index', array('partidoId' => $partido->id, 'totalGoles' => $partido->golesl+$partido->golesv))}}" class="btn btn-info m-1">Goles</a>

                                    </div>

                                </td>
                            </tr>
                        @endif
                    @endforeach
                </table>
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-9">
                        {{ $diferencia->links() }}
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-2">
                        <strong>Total: {{ $diferencia->total() }}</strong>
                    </div>
                </div>
            </div>

        </div>
            </div>
        </div>

        <a href="{{ route('torneos.index') }}" class="btn btn-success m-1">Volver</a>

    </div>
@endsection
