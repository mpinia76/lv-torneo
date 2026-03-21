@extends('layouts.app')

@section('pageTitle', 'Estadísticas manuales de tecnico')

@section('content')
    <div class="container">
        <div class="row mb-4 align-items-center">
            <div class="col-md-3 text-center">
                <img src="{{ $tecnico->persona->foto ? url('images/'.$tecnico->persona->foto) : url('images/sin_foto.png') }}"
                     alt="Foto tecnico" class="img-fluid mb-2" style="max-height: 200px;">
                <div class="mb-2">
                    <img src="{{ $tecnico->persona->bandera_url }}" alt="{{ $tecnico->persona->nacionalidad }}" height="25">
                </div>
            </div>

            <div class="col-md-9">
                <div class="row mb-2">
                    <div class="col-md-3"><strong>Nombre</strong><br>{{ $tecnico->persona->name }}</div>
                    <div class="col-md-3"><strong>Completo</strong><br>{{ $tecnico->persona->nombre }} {{ $tecnico->persona->apellido }}</div>
                    <div class="col-md-3"><strong>Ciudad Nacimiento</strong><br>{{ $tecnico->persona->ciudad }}</div>
                    <div class="col-md-3"><strong>Edad</strong><br>
                        {!! $tecnico->persona->fallecimiento ? '<img src="'.url('images/death.png').'" alt="Fallecido" height="20">' : '' !!}
                        {{ $tecnico->persona->nacimiento ? $tecnico->persona->getAgeAttribute() : '' }}
                    </div>
                </div>

                @if($tecnico->persona->observaciones)
                    <div class="row mb-2">
                        <div class="col-12"><strong>Observaciones:</strong><br>{{ $tecnico->persona->observaciones }}</div>
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

        <a class="btn btn-success m-1" href="{{route('tecnico-estadisticas.createPorTecnico', $tecnico->id)}}">Nueva</a>



        <table class="table">
            <thead>
            <th>Torneo</th>
            <th>Equipo</th>
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

                    <td>
                        @if($stat->equipo && $stat->equipo->escudo)
                            <img src="{{ url('images/'.$stat->equipo->escudo) }}" height="25">
                        @endif
                        {{ $stat->equipo->nombre ?? 'N/D' }}
                    </td>
                    <td>{{ $stat->posicion }}</td>
                    <td>{{ $stat->partidos }}</td>
                    <td>{{ $stat->ganados }}</td>
                    <td>{{ $stat->empatados }}</td>
                    <td>{{ $stat->perdidos }}</td>
                    <td>{{ $stat->goles_favor }}</td>

                    <td>{{ $stat->goles_en_contra }}</td>
                    <td>
                        <div class="d-flex">
                            <a href="{{route('tecnico-estadisticas.edit', $stat->id)}}" class="btn btn-primary m-1">Editar</a>

                            <form action="{{ route('tecnico-estadisticas.destroy', $stat->id) }}" method="POST" onsubmit="return ConfirmDelete()">
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
