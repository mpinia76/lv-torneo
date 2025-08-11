@extends('layouts.app')

@section('pageTitle', 'Buscar plantillas en '.$torneo->nombre)

@section('content')
    <div class="container">
        <h1 class="display-6">Buscar plantillas en torneo {{ $torneo->nombre }} - {{ $torneo->year }}</h1>

        <form method="GET" action="{{ route('plantillas.buscarPorTorneo', $torneo->id) }}" class="form-inline mb-3">
            <input type="text" name="buscarpor" value="{{ $nombre }}" class="form-control mr-2" placeholder="Buscar equipo">
            <button class="btn btn-primary">Buscar</button>
            <a href="{{ route('torneos.show', $torneo->id) }}" class="btn btn-secondary ml-2">Volver</a>
        </form>

        @if($plantillas->isEmpty())
            <div class="alert alert-warning">No se encontraron plantillas para esa búsqueda.</div>
        @else
            <table class="table">
                <thead>
                <th>#</th>
                <th>Equipo</th>
                <th>Grupo</th>
                <th>Acciones</th>
                </thead>
                @foreach($plantillas as $index => $plantilla)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            @if($plantilla->equipo->escudo)
                                <img src="{{ url('images/'.$plantilla->equipo->escudo) }}" height="25" alt="Escudo">
                            @endif
                            {{ $plantilla->equipo->nombre }}
                        </td>
                        <td>{{ $plantilla->grupo->nombre }}</td>
                        <td>
                            <a href="{{ route('plantillas.edit', $plantilla->id) }}" class="btn btn-primary btn-sm">Editar</a>
                            <form action="{{ route('plantillas.destroy', $plantilla->id) }}" method="POST" style="display:inline" onsubmit="return ConfirmDelete()">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-danger btn-sm">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>

    <script>
        function ConfirmDelete() {
            return confirm('Está seguro?');
        }
    </script>
@endsection
