@extends('layouts.appPublic')

@section('pageTitle', 'Listado de fechas')

@section('content')
    <div class="container">


    <hr/>


        <nav class="navbar navbar-light float-right" style="width: 60%">
            <form class="form-inline">
                <input type="hidden" name="grupoId" value="{{ (isset($_GET['grupoId']))?$_GET['grupoId']:'' }}">
                <input  value="{{ (isset($_GET['buscarpor']))?$_GET['buscarpor']:'' }}" name="buscarpor" class="form-control mr-sm-2" type="search" placeholder="Buscar" aria-label="Search">

                <button class="btn btn-success m-1" type="submit">Buscar</button>
            </form>
        </nav>

    <table class="table" style="width: 60%">
        <thead>
        <th>Numero</th>

        <th>Torneo</th>

        <th colspan="3"></th>
        </thead>

        @foreach($fechas as $fecha)

            <tr>
                <td>{{$fecha->numero}}</td>

                <td>{{$fecha->grupo->torneo->nombre}} - {{$fecha->grupo->torneo->year}}</td>

                <td>
                    <div class="d-flex">


                        <a href="{{route('fechas.showPublic',array('fechaId' => $fecha->id))}}" class="btn btn-success m-1">Ver</a>



                    </div>

                </td>
            </tr>
        @endforeach
    </table>
        <div class="d-flex">

            <a href="{{route('torneos.ver',array('torneoId' => $grupo->torneo->id))}}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>

@endsection
