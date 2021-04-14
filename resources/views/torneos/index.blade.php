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
        <nav class="navbar navbar-light float-right">
            <form class="form-inline">

                <input  value="{{ (isset($_GET['buscarpor']))?$_GET['buscarpor']:'' }}" name="buscarpor" class="form-control mr-sm-2" type="search" placeholder="Buscar" aria-label="Search">

                <button class="btn btn-success m-1" type="submit">Buscar</button>
            </form>
        </nav>

    <table class="table">
        <thead>
        <th>Nombre</th>
        <th>Año</th>
        <th>Nro. de equipos</th>
        <th>Nro. de grupos</th>

        <th colspan="3"></th>
        </thead>

        @foreach($torneos as $torneo)
            <tr>
                <td>{{$torneo->nombre}}</td>
                <td>{{$torneo->year}}</td>
                <td>{{$torneo->equipos}}</td>
                <td>{{$torneo->grupos}}</td>

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
