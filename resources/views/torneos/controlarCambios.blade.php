@extends('layouts.app')

@section('pageTitle', 'Controlar cambios')

@section('content')
    <div class="container">
        <h1 class="display-6">Controlar cambios</h1>

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

        <h1 class="display-6">No están en la alineación</h1>

        <!-- build our form inputs -->
        <div class="row">

            <div class="form-group col-md-12">
                <h1 class="display-6">Partidos</h1>
                <table class="table">
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

        <h1 class="display-6">Cambios repetidos</h1>

        <!-- build our form inputs -->
        <div class="row">

            <div class="form-group col-md-12">
                <h1 class="display-6">Partidos</h1>
                <table class="table">
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


                    @foreach($cambios as $cambio)

                        @if($cambio->dia)
                            <tr>
                                <td>@if($cambio->jugador_foto)
                                        <img id="original" class="imgCircle" src="{{ url('images/'.$cambio->jugador_foto) }}" >
                                    @else
                                        <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                                    @endif{{$cambio->jugador_apellido}}, {{$cambio->jugador_nombre}}</td>
                                <td>{{$cambio->torneo}} {{$cambio->year}}</td>
                                <td>{{$cambio->fecha}}</td>
                                <td>{{($cambio->dia)?date('d/m/Y H:i', strtotime($cambio->dia)):''}}</td>
                                <td>@if($cambio->equipo_local_nombre)
                                        @if($cambio->equipo_local_escudo)<img id="original" src="{{ url('images/'.$cambio->equipo_local_escudo) }}" height="20">
                                        @endif
                                        {{$cambio->equipo_local_nombre}}
                                    @endif
                                </td>
                                <td>{{$cambio->golesl}}
                                    @if($cambio->penalesl)
                                        ({{$cambio->penalesl}})
                                    @endif
                                </td>
                                <td>{{$cambio->golesv}}
                                    @if($cambio->penalesv)
                                        ({{$cambio->penalesv}})
                                    @endif
                                </td>
                                <td>@if($cambio->equipo_visitante_nombre)
                                        @if($cambio->equipo_visitante_escudo)<img id="original" src="{{ url('images/'.$cambio->equipo_visitante_escudo) }}" height="20">
                                        @endif
                                        {{$cambio->equipo_visitante_nombre}}
                                    @endif
                                </td>

                                <td>
                                    <div class="d-flex">


                                        <a href="{{route('cambios.index', array('partidoId' => $cambio->id))}}" class="btn btn-info m-1">Cambios</a>

                                    </div>

                                </td>
                            </tr>
                        @endif
                    @endforeach
                </table>
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-9">
                        {{ $cambios->links() }}
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-2">
                        <strong>Total: {{ $cambios->total() }}</strong>
                    </div>
                </div>
            </div>

        </div>

        <h1 class="display-6">Cambios impares</h1>

        <!-- build our form inputs -->
        <div class="row">

            <div class="form-group col-md-12">
                <h1 class="display-6">Partidos</h1>
                <table class="table">
                    <thead>

                    <th>Torneo</th>
                    <th>Fecha</th>
                    <th>Día</th>
                    <th>Local</th>
                    <th>GL</th>
                    <th>GV</th>
                    <th>Visitante</th>

                    </thead>


                    @foreach($impares as $cambio)

                        @if($cambio->dia)
                            <tr>

                                <td>{{$cambio->torneo}} {{$cambio->year}}</td>
                                <td>{{$cambio->fecha}}</td>
                                <td>{{($cambio->dia)?date('d/m/Y H:i', strtotime($cambio->dia)):''}}</td>
                                <td>@if($cambio->equipo_local_nombre)
                                        @if($cambio->equipo_local_escudo)<img id="original" src="{{ url('images/'.$cambio->equipo_local_escudo) }}" height="20">
                                        @endif
                                        {{$cambio->equipo_local_nombre}}
                                    @endif
                                </td>
                                <td>{{$cambio->golesl}}
                                    @if($cambio->penalesl)
                                        ({{$cambio->penalesl}})
                                    @endif
                                </td>
                                <td>{{$cambio->golesv}}
                                    @if($cambio->penalesv)
                                        ({{$cambio->penalesv}})
                                    @endif
                                </td>
                                <td>@if($cambio->equipo_visitante_nombre)
                                        @if($cambio->equipo_visitante_escudo)<img id="original" src="{{ url('images/'.$cambio->equipo_visitante_escudo) }}" height="20">
                                        @endif
                                        {{$cambio->equipo_visitante_nombre}}
                                    @endif
                                </td>

                                <td>
                                    <div class="d-flex">


                                        <a href="{{route('cambios.index', array('partidoId' => $cambio->id))}}" class="btn btn-info m-1">Cambios</a>

                                    </div>

                                </td>
                            </tr>
                        @endif
                    @endforeach
                </table>
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-9">
                        {{ $cambios->links() }}
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-2">
                        <strong>Total: {{ $cambios->total() }}</strong>
                    </div>
                </div>
            </div>

        </div>

        <a href="{{ route('torneos.index') }}" class="btn btn-success m-1">Volver</a>

    </div>
@endsection
