@extends('layouts.app')

@section('pageTitle', 'Listado de jugadores que no jugaron')

@section('content')
    <div class="container">
        <h1 class="display-6">No jugaron</h1>

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

        <form action="{{ route('plantilla.eliminarSeleccionados') }}" method="POST" onsubmit="return ConfirmDelete()">
            @csrf
            <input type="hidden" name="grupoId" value="{{ $grupo_id }}">
            <button type="button" class="btn btn-primary" id="seleccionarTodosControlarPlantillas">Seleccionar Todos</button>
            <button type="submit" class="btn btn-danger">Eliminar Seleccionados</button>
            <table class="table">
                <thead>
                <th>#</th>
                <th>Posición</th>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>Dorsal</th>
                <th>Equipo</th>
                <th colspan="3"></th>
                </thead>

                @foreach($jugadores as $jugador)
                    <tr>
                        <td>
                            <input type="checkbox" name="jugador_ids[]" value="{{ $jugador->id }}">
                        </td>
                        <td>
                            @if($jugador->foto)
                                <img id="original" class="imgCircle" src="{{ url('images/'.$jugador->foto) }}" >
                            @else
                                <img id="original" class="imgCircle" src="{{ url('images/sin_foto.png') }}" >
                            @endif{{$jugador->tipoJugador}}
                        </td>
                        <td>{{$jugador->apellido}}</td>
                        <td>{{$jugador->nombre}}</td>
                        <td>{{$jugador->dorsal}}</td>
                        <td>
                            @if($jugador->escudo)
                                <img id="original" src="{{ url('images/'.$jugador->escudo) }}" height="20">
                            @endif{{$jugador->equipo}}
                        </td>
                        <td>
                            <div class="d-flex">
                                <form action="{{ route('plantilla.destroy', $jugador->id) }}" method="POST" onsubmit="return ConfirmDelete()">
                                    @method('DELETE')
                                    @csrf
                                    <button class="btn btn-danger m-1">Eliminar</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </table>

        </form>

        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-9">
                {{ $jugadores->links() }}
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $jugadores->total() }}</strong>
            </div>
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
