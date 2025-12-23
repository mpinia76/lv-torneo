@extends('layouts.app')

@section('pageTitle', 'Verificar nombre largo')

@section('content')
    <div class="container">
        <h1 class="display-6">Verificar nombre largo (scraping)</h1>

        <hr/>

        @if (\Session::has('status'))
            <div class="alert alert-success">
                {{ \Session::get('status') }}
            </div>
        @endif
        <form method="POST" action="{{ route('jugadores.confirmarNombresLargos') }}">
            @csrf
            <button type="button"
                    class="btn btn-primary mb-2"
                    id="seleccionarTodos">
                Seleccionar todos
            </button>

            <button type="submit"
                    class="btn btn-success mb-2"
                    onclick="return confirm('¿Confirmar los nombres seleccionados?')">
                Confirmar seleccionados
            </button>
        <table class="table">
            <thead>
            <th>
                <input type="checkbox" id="checkAll">
            </th>
            <th>Posición</th>
            <th>Actual</th>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>Sugerido</th>
            <th>Edad</th>
            <th>Ciudad</th>
            <th>Acciones</th>
            </thead>

            @foreach($jugadores as $jugador)
                <tr>
                    <td>
                        @if($jugador->nombre_sugerido)
                            <input type="checkbox"
                                   name="personas[{{ $jugador->persona_id }}]"
                                   value="{{ $jugador->nombre_sugerido }}">
                        @endif
                    </td>
                    <td>
                        @if($jugador->foto)
                            <img class="imgCircle" src="{{ url('images/'.$jugador->foto) }}">
                        @else
                            <img class="imgCircle" src="{{ url('images/sin_foto.png') }}">
                        @endif

                        <img src="{{ $jugador->persona->bandera_url }}"
                             alt="{{ $jugador->persona->nacionalidad }}">
                    </td>

                    <td>{{ $jugador->tipoJugador }}</td>

                    <td>
                        <strong>{{ $jugador->name }}</strong>
                    </td>

                    <td>{{ $jugador->apellido }}</td>
                    <td>{{ $jugador->nombre }}</td>

                    <td>
                        @if($jugador->nombre_sugerido)
                            <span class="text-success fw-bold">
                        {{ $jugador->nombre_sugerido }}
                    </span>
                            <br>
                            <a href="{{ $jugador->url_sugerida }}" target="_blank">
                                Ver fuente
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>

                    <td>{{ ($jugador->nacimiento) ? $jugador->persona->getAgeWithDateAttribute() : '' }}</td>
                    <td>{{ $jugador->ciudad }}</td>

                    <td>
                        @if($jugador->nombre_sugerido)
                            <div class="d-flex flex-wrap">

                                {{-- Confirmar --}}
                                <form method="POST"
                                      action="{{ route('jugadores.confirmarNombreLargo', $jugador->persona_id) }}"
                                      class="m-1">
                                    @csrf
                                    <input type="hidden" name="name" value="{{ $jugador->nombre_sugerido }}">
                                    <button class="btn btn-success btn-sm">
                                        ✔ Confirmar
                                    </button>
                                </form>

                                {{-- Editar persona --}}


                            </div>
                        @else
                            <span class="text-muted">Sin sugerencia</span>
                        @endif
                            <a href="{{route('jugadores.edit', $jugador->id)}}" class="btn btn-primary m-1">Editar</a>
                    </td>
                </tr>
            @endforeach
        </table>
        </form>
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-9">
                {{ $jugadores->appends($data)->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $jugadores->total() }}</strong>


                <script>
                    document.getElementById('seleccionarTodos')?.addEventListener('click', function () {
                        document
                            .querySelectorAll('input[type=checkbox][name^="personas"]')
                            .forEach(cb => cb.checked = true);
                    });
                </script>

@endsection
