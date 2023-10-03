@extends('layouts.app')

@section('pageTitle', 'Listado de plantillas')

@section('content')
    <div class="container">
    <h1 class="display-6">Plantillas del grupo {{$grupo->nombre}} de {{$grupo->torneo->nombre}} - {{$grupo->torneo->year}}</h1>

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
        <a class="btn btn-success m-1" href="{{route('plantillas.create',  array('grupoId' => (isset($_GET['grupoId']))?$_GET['grupoId']:'' ))}}">Nuevo</a>
        <a class="btn btn-info m-1" href="{{route('plantillas.import',  array('grupoId' => (isset($_GET['grupoId']))?$_GET['grupoId']:'' ))}}">Importar datos</a>
        <nav class="navbar navbar-light float-right">
            <form class="form-inline">
                <input type="hidden" name="grupoId" value="{{ (isset($_GET['grupoId']))?$_GET['grupoId']:'' }}">
                <input  value="{{ (isset($_GET['buscarpor']))?$_GET['buscarpor']:session('nombre_filtro_plantilla') }}" name="buscarpor" class="form-control mr-sm-2" type="search" placeholder="Buscar" aria-label="Search">

                <button class="btn btn-success m-1" type="submit">Buscar</button>
            </form>
        </nav>

    <table class="table">
        <thead>
        <th>Equipo</th>

        <th>Grupo</th>

        <th colspan="3"></th>
        </thead>
        @php
            $i = 1;
        @endphp
        @foreach($plantillas as $plantilla)

            <tr>
                <td>
                    {{$i++}}
                    @if($plantilla->equipo->escudo)
                        <img id="original" src="{{ url('images/'.$plantilla->equipo->escudo) }}" height="25">
                    @endif{{$plantilla->equipo->nombre}}</td>

                <td>{{$plantilla->grupo->torneo->nombre}} - {{$plantilla->grupo->torneo->year}}</td>

                <td>
                    <div class="d-flex">

                        <a href="{{route('plantillas.edit', $plantilla->id)}}" class="btn btn-primary m-1">Editar</a>



                    </div>

                </td>
            </tr>
        @endforeach
    </table>

        <div class="d-flex">

            <a href="{{ route('torneos.show',$grupo->torneo->id)}}" class="btn btn-success m-1">Volver</a>
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
