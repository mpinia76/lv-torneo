@extends('layouts.app')

@section('pageTitle', 'Estadísticas manuales de jugador')

@section('content')
    <div class="container">
        <div class="row mb-4 align-items-center">
            <div class="col-md-3 text-center">
                <img src="{{ $jugador->persona->foto ? url('images/'.$jugador->persona->foto) : url('images/sin_foto.png') }}"
                     alt="Foto jugador" class="img-fluid mb-2" style="max-height: 200px;">
                <div class="mb-2">
                    <img src="{{ $jugador->persona->bandera_url }}" alt="{{ $jugador->persona->nacionalidad }}" height="25">
                </div>
            </div>

            <div class="col-md-9">
                <div class="row mb-2">
                    <div class="col-md-3"><strong>Nombre</strong><br>{{ $jugador->persona->name }}</div>
                    <div class="col-md-3"><strong>Completo</strong><br>{{ $jugador->persona->nombre }} {{ $jugador->persona->apellido }}</div>
                    <div class="col-md-3"><strong>Ciudad Nacimiento</strong><br>{{ $jugador->persona->ciudad }}</div>
                    <div class="col-md-3"><strong>Edad</strong><br>
                        {!! $jugador->persona->fallecimiento ? '<img src="'.url('images/death.png').'" alt="Fallecido" height="20">' : '' !!}
                        {{ $jugador->persona->nacimiento ? $jugador->persona->getAgeAttribute() : '' }}
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-3"><strong>Posición</strong><br>{{ $jugador->tipoJugador }}</div>
                    <div class="col-md-3"><strong>Altura</strong><br>{{ $jugador->persona->altura }} m</div>
                    <div class="col-md-3"><strong>Peso</strong><br>{{ $jugador->persona->peso }} kg</div>
                </div>
                @if($jugador->persona->observaciones)
                    <div class="row mb-2">
                        <div class="col-12"><strong>Observaciones:</strong><br>{{ $jugador->persona->observaciones }}</div>
                    </div>
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

        <a class="btn btn-success m-1" href="{{route('jugador-estadisticas.createPorJugador', $jugador->id)}}">Nueva</a>



        <table class="table">
            <thead>
            <th>Torneo</th>
            <th>Equipo</th>
            <th>Posición</th>
            <th>Partidos</th>
            <th>G. Jugada</th>
            <th>G. Cabeza</th>
            <th>G. Penal</th>
            <th>G. T. Libre</th>
            <th>G. en contra</th>
            <th>T. amarillas</th>
            <th>T. rojas</th>
            <th>P. errados</th>
            <th>P. atajados</th>
            <th>G. recibidos</th>
            <th>V. invictas</th>
            <th>P. atajó</th>
            <th></th>
            </thead>

            @foreach($stats as $stat)
                <tr>
                    <td>
                        @if($stat->torneo_nombre && $stat->torneo_logo)
                            <img src="{{ url('images/'.$stat->torneo_logo) }}" height="25">
                        @endif
                        {{ $stat->torneo_nombre }}</td>

                    <td>
                        @if($stat->equipo && $stat->equipo->escudo)
                            <img src="{{ url('images/'.$stat->equipo->escudo) }}" height="25">
                        @endif
                        {{ $stat->equipo->nombre ?? 'N/D' }}
                    </td>
                    <td>{{ $stat->posicion }}</td>
                    <td>{{ $stat->partidos }}</td>
                    <td>{{ $stat->goles_jugada }}</td>
                    <td>{{ $stat->goles_cabeza }}</td>
                    <td>{{ $stat->goles_penal }}</td>
                    <td>{{ $stat->goles_tiro_libre }}</td>
                    <td>{{ $stat->goles_en_contra }}</td>
                    <td>{{ $stat->amarillas }}</td>
                    <td>{{ $stat->rojas }}</td>
                    <td>{{ $stat->penales_errados }}</td>
                    <td>{{ $stat->penales_atajados }}</td>
                    <td>{{ $stat->goles_recibidos }}</td>
                    <td>{{ $stat->vallas_invictas }}</td>
                    <td>{{ $stat->penales_atajo }}</td>
                    <td>
                        <div class="d-flex">
                            <a href="{{route('jugador-estadisticas.edit', $stat->id)}}" class="btn btn-primary m-1">Editar</a>

                            <form action="{{ route('jugador-estadisticas.destroy', $stat->id) }}" method="POST" onsubmit="return ConfirmDelete()">
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
