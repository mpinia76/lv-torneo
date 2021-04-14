@extends('layouts.app')

@section('pageTitle', 'Ver torneo')

@section('content')
    <div class="container">
    <h1 class="display-6">Ver torneo</h1>

    <hr/>

    <dl>
        <dt>Nombre</dt>
        <dd>{{$torneo->nombre}}</dd>

        <dt>Año</dt>
        <dd>{{$torneo->year}}</dd>

        <dt>Nro. de equipos</dt>
        <dd>{{$torneo->equipos}}</dd>

        <dt>Nro. de grupos</dt>
        <dd>{{$torneo->grupos}}</dd>

        <dt>Playoffs</dt>
        <dd>{{$torneo->playoffs}}</dd>
    </dl>

        <div class="d-flex">
            <a href="{{route('grupos.goleadores',  array('torneoId' => $torneo->id))}}" class="btn btn-info m-1">Goleadores</a>
            <a href="{{route('grupos.tarjetas',  array('torneoId' => $torneo->id))}}" class="btn btn-primary m-1">Tarjetas</a>
            <a href="{{route('torneos.promedios',  array('torneoId' => $torneo->id))}}" class="btn btn-success m-1">Promedios</a>
        </div>
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
                            <a href="{{route('plantillas.index',  array('grupoId' => $grupo->id))}}" class="btn btn-info m-1">Plantillas</a>
                        </div>

                    </td>
                </tr>
            @endforeach
        </table>

    <div class="d-flex">
        <a href="{{route('torneos.edit', $torneo->id)}}" class="btn btn-primary m-1">Editar</a>

        <form action="{{ route('torneos.destroy', $torneo->id) }}" method="POST" onsubmit="return  ConfirmDelete()">
            <input type="hidden" name="_method" value="DELETE">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <button class="btn btn-danger m-1">Eliminar</button>
        </form>
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
