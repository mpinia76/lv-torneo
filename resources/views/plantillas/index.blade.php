@extends('layouts.app')

@section('pageTitle', 'Listado de plantillas')

@section('content')
    <div class="container">
    <h1 class="display-6">Plantillas de {{$torneo->nombre}} - {{$torneo->year}}</h1>

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
        <a class="btn btn-success m-1" href="{{route('plantillas.create',  array('torneoId' => (isset($_GET['torneoId']))?$_GET['torneoId']:'' ))}}">Nuevo</a>
        <nav class="navbar navbar-light float-right">
            <form class="form-inline">
                <input type="hidden" name="torneoId" value="{{ (isset($_GET['torneoId']))?$_GET['torneoId']:'' }}">
                <input  value="{{ (isset($_GET['buscarpor']))?$_GET['buscarpor']:'' }}" name="buscarpor" class="form-control mr-sm-2" type="search" placeholder="Buscar" aria-label="Search">

                <button class="btn btn-success m-1" type="submit">Buscar</button>
            </form>
        </nav>

    <table class="table">
        <thead>
        <th>Equipo</th>

        <th>Torneo</th>

        <th colspan="3"></th>
        </thead>

        @foreach($plantillas as $plantilla)

            <tr>
                <td>@if($plantilla->equipo->escudo)
                        <img id="original" src="{{ url('images/'.$plantilla->equipo->escudo) }}" height="25">
                    @endif{{$plantilla->equipo->nombre}}</td>

                <td>{{$plantilla->torneo->nombre}} - {{$plantilla->torneo->year}}</td>

                <td>
                    <div class="d-flex">

                        <a href="{{route('plantillas.edit', $plantilla->id)}}" class="btn btn-primary m-1">Editar</a>



                    </div>

                </td>
            </tr>
        @endforeach
    </table>

        <div class="d-flex">

            <a href="{{route('torneos.index')}}" class="btn btn-success m-1">Volver</a>
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
