@extends('layouts.appPublic')

@section('pageTitle', 'Ver torneo')

@section('content')
    <div class="container">


    <hr/>



        <h1 class="display-6">{{$torneo->nombre}} - {{$torneo->year}}</h1>

        <div class="d-flex">
            @if (count($torneo->grupoDetalle)==1)
                <a href="{{route('fechas.ver',  array('grupoId' => $torneo->grupoDetalle[0]->id))}}" class="btn btn-success m-1">Fechas</a>
                <a href="{{route('grupos.posicionesPublic',  array('grupoId' => $torneo->grupoDetalle[0]->id))}}" class="btn btn-primary m-1">Posiciones</a>
            @endif
            <a href="{{route('grupos.goleadoresPublic',  array('torneoId' => $torneo->id))}}" class="btn btn-info m-1">Goleadores</a>
            <a href="{{route('grupos.tarjetasPublic',  array('torneoId' => $torneo->id))}}" class="btn btn-primary m-1">Tarjetas</a>
        </div>
        @if (count($torneo->grupoDetalle)>1)
        <h1 class="display-6">Grupos</h1>

        <hr/>



        <table class="table">
            <thead>
            <th>Nombre</th>

            <th>Nro. de equipos</th>


            <th colspan="3"></th>
            </thead>

            @foreach($torneo->grupoDetalle as $grupo)
                <tr>
                    <td>{{$grupo->nombre}}</td>

                    <td>{{$grupo->equipos}}</td>

                    <td>
                        <div class="d-flex">
                            <a href="{{route('fechas.index',  array('grupoId' => $grupo->id))}}" class="btn btn-success m-1">Fechas</a>
                            <a href="{{route('grupos.posiciones',  array('grupoId' => $grupo->id))}}" class="btn btn-primary m-1">Posiciones</a>

                        </div>

                    </td>
                </tr>
            @endforeach
        </table>
        @endif

    </div>

@endsection
