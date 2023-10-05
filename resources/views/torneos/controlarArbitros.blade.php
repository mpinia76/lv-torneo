@extends('layouts.app')

@section('pageTitle', 'Controlar Arbitros')

@section('content')
    <div class="container">
        <h1 class="display-6">Controlar arbitros</h1>

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

        <h1 class="display-6">Sin árbitro principal</h1>

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


                    @foreach($partidos as $partido)

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

                                        <a href="{{route('partidos.arbitros', array('partidoId' => $partido->id))}}" class="btn btn-success m-1">Jueces</a>

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

        <h1 class="display-6">Distintos de 3</h1>

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


                    @foreach($jueces as $partido)

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

                                        <a href="{{route('partidos.arbitros', array('partidoId' => $partido->id))}}" class="btn btn-success m-1">Jueces</a>

                                    </div>

                                </td>
                            </tr>
                        @endif
                    @endforeach
                </table>
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-9">
                        {{ $jueces->links() }}
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-2">
                        <strong>Total: {{ $jueces->total() }}</strong>
                    </div>
                </div>
            </div>

        </div>

        <h1 class="display-6">Repetidos</h1>

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


                    @foreach($repetidos as $partido)

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

                                        <a href="{{route('partidos.arbitros', array('partidoId' => $partido->id))}}" class="btn btn-success m-1">Jueces</a>

                                    </div>

                                </td>
                            </tr>
                        @endif
                    @endforeach
                </table>
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-6 col-md-9">
                        {{ $repetidos->links() }}
                    </div>
                    <div class="form-group col-xs-12 col-sm-6 col-md-2">
                        <strong>Total: {{ $repetidos->total() }}</strong>
                    </div>
                </div>
            </div>

        </div>


        <a href="{{ route('torneos.index') }}" class="btn btn-success m-1">Volver</a>

    </div>
@endsection
