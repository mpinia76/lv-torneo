@extends('layouts.app')

@section('pageTitle', 'Listado de torneos')

@section('content')
    <div class="container">
    <h1 class="display-6">Torneos</h1>

    <hr/>
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
        <a class="btn btn-success m-1" href="{{route('torneos.create')}}">Nuevo</a>
        <a class="btn btn-info m-1" href="{{route('partidos.controlarAlineaciones')}}">Controlar alineaciones</a>
        <a class="btn btn-info m-1" href="{{route('partidos.controlarGoles')}}">Controlar goles</a>
        <a class="btn btn-info m-1" href="{{route('partidos.controlarTarjetas')}}">Controlar tarjetas</a>
        <a class="btn btn-info m-1" href="{{route('partidos.controlarCambios')}}">Controlar cambios</a>
        <a class="btn btn-info m-1" href="{{route('partidos.controlarArbitros')}}">Controlar arbitros</a>
        <a class="btn btn-info m-1" href="{{route('partidos.controlarTecnicos')}}">Controlar técnicos</a>
        <nav class="navbar navbar-light float-right">
            <form class="form-inline">

                <input  value="{{ (isset($_GET['buscarpor']))?$_GET['buscarpor']:session('nombre_filtro_torneo')}}" name="buscarpor" class="form-control mr-sm-2" type="search" placeholder="Buscar" aria-label="Search">

                <button class="btn btn-success m-1" type="submit">Buscar</button>
            </form>
        </nav>

    <table class="table">
        <thead>
        <th>Nombre</th>
        <th>Año</th>
        <th>Nro. de equipos</th>
        <th>Nro. de grupos</th>
        <th>Tipo</th>
        <th>Ambito</th>
        <th colspan="3"></th>
        </thead>

        @foreach($torneos1 as $torneo)
            <tr>
                <td>@if($torneo->escudo)
                        <img id="original" src="{{ url('images/'.$torneo->escudo) }}" height="25">
                    @endif {{$torneo->nombre}}</td>
                <td>{{$torneo->year}}</td>
                <td>{{$torneo->equipos}}</td>
                <td>{{$torneo->grupos}}</td>
                <td>{{$torneo->tipo}}</td>
                <td>{{$torneo->ambito}}</td>
                <td>
                    <div class="d-flex">
                        <a href="{{route('torneos.show', $torneo->id)}}" class="btn btn-info m-1">Ver</a>
                        <a href="{{route('torneos.edit', $torneo->id)}}" class="btn btn-primary m-1">Editar</a>

                        <form action="{{ route('torneos.destroy', $torneo->id) }}" method="POST" onsubmit="return  ConfirmDelete()">
                            <input type="hidden" name="_method" value="DELETE">
                            <input type="hidden" name="_token" value="{{ csrf_token() }}">
                            <button class="btn btn-danger m-1">Eliminar</button>
                        </form>

                    </div>

                </td>
            </tr>
        @endforeach
    </table>
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-9">
                {{ $torneos1->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $torneos1->total() }}</strong>
            </div>
        </div>
    </div>
    <script>

        function ConfirmDelete()
        {
            var x = confirm("Está seguro?");
            if (x)
                return true;
            else
                return false;
        }

    </script>
@endsection
