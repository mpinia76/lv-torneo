@extends('layouts.app')

@section('pageTitle', 'Estadísticas manuales de equipo')
<style>


    /* --- Equipo info --- */
    dd img {
        margin-left: 5px;
        vertical-align: middle;
    }
</style>
@section('content')
    <div class="container">
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <dt>Nombre</dt>
                        <dd>{{$equipo->nombre}} <img src="{{ url('images/'.removeAccents($equipo->pais).'.gif') }}" alt="{{ $equipo->pais }}"></dd>
                    </div>
                    <div class="col-md-6 mb-2">
                        <dt>Socios</dt>
                        <dd>{{$equipo->socios}}</dd>
                    </div>
                    <div class="col-md-6 mb-2">
                        <dt>Fundación</dt>
                        <dd>{{date('d/m/Y', strtotime($equipo->fundacion))}} - {{Carbon::parse($equipo->fundacion)->age}} años</dd>
                    </div>
                    <div class="col-md-6 mb-2">
                        <dt>Estadio</dt>
                        <dd>{{$equipo->estadio}}</dd>
                    </div>
                </div>
            </div>

            <div class="col-md-4 d-flex justify-content-center align-items-center">
                @if($equipo->escudo)
                    <img src="{{ url('images/'.$equipo->escudo) }}" style="width: 200px" class="img-fluid">
                @endif
            </div>
        </div>
        <hr/>

        @if (Session::has('error'))
            <div class="alert alert-danger"><ul><li>{{ Session::get('error') }}</li></ul></div>
        @endif
        @if (Session::has('success'))
            <div class="alert alert-success"><ul><li>{{ Session::get('success') }}</li></ul></div>
        @endif

        <a class="btn btn-success m-1" href="{{route('equipo-estadisticas.createPorEquipo', $equipo->id)}}">Nueva</a>



        <table class="table">
            <thead>
            <th>Torneo</th>
            <th>Posición</th>
            <th>Partidos</th>
            <th>Ganados</th>
            <th>Empatados</th>
            <th>Perdidos</th>
            <th>G. Favor</th>
            <th>G. en contra</th>

            <th></th>
            </thead>

            @foreach($stats as $stat)
                <tr>
                    <td>
                        @if($stat->torneo_nombre && $stat->torneo_logo)
                            <img src="{{ url('images/'.$stat->torneo_logo) }}" height="25">
                        @endif
                        {{ $stat->torneo_nombre }}</td>


                    <td>{{ $stat->posicion }}</td>
                    <td>{{ $stat->partidos }}</td>
                    <td>{{ $stat->ganados }}</td>
                    <td>{{ $stat->empatados }}</td>
                    <td>{{ $stat->perdidos }}</td>
                    <td>{{ $stat->goles_favor }}</td>

                    <td>{{ $stat->goles_en_contra }}</td>
                    <td>
                        <div class="d-flex">
                            <a href="{{route('equipo-estadisticas.edit', $stat->id)}}" class="btn btn-primary m-1">Editar</a>

                            <form action="{{ route('equipo-estadisticas.destroy', $stat->id) }}" method="POST" onsubmit="return ConfirmDelete()">
                                @method('DELETE')
                                @csrf
                                <button class="btn btn-danger m-1">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
        </table>

        {{ $stats->links() }}
    </div>

    <script>
        function ConfirmDelete() {
            return confirm("Está seguro?");
        }
    </script>
@endsection
