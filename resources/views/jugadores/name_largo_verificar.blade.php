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
            <th></th>
            <th>Tipo</th>
            <th>Actual</th>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>Sugerido</th>
            <th>Edad</th>
            <th>Ciudad</th>
            <th>Acciones</th>
            </thead>

            @foreach($personas as $jugador)
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

                    <td>
                        @switch($jugador->tipo)
                            @case('jugador') 🏃 Jugador @break
                            @case('tecnico') 🧑‍🏫 Técnico @break
                            @case('arbitro') 🟨 Árbitro @break
                        @endswitch
                    </td>

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



                            <button type="submit"
                                    class="btn btn-success btn-sm"
                                    name="name"
                                    value="{{ $jugador->nombre_sugerido }}"
                                    formaction="{{ route('jugadores.confirmarNombreLargo', $jugador->persona_id) }}"
                                    onclick="return confirm('¿Confirmar este nombre?')">
                                ✔ Confirmar
                            </button>

                        @else
                            <span class="text-muted">Sin sugerencia</span>
                        @endif

                            {{-- 🔥 EDITAR SEGÚN TIPO --}}
                            @switch($jugador->tipo)

                                @case('jugador')
                                    <a href="{{ route('jugadores.edit', $jugador->modelo_id) }}"
                                       class="btn btn-primary btn-sm m-1">
                                        Editar jugador
                                    </a>
                                    @break

                                @case('tecnico')
                                    <a href="{{ route('tecnicos.edit', $jugador->modelo_id) }}"
                                       class="btn btn-warning btn-sm m-1">
                                        Editar técnico
                                    </a>
                                    @break

                                @case('arbitro')
                                    <a href="{{ route('arbitros.edit', $jugador->modelo_id) }}"
                                       class="btn btn-info btn-sm m-1">
                                        Editar árbitro
                                    </a>
                                    @break

                            @endswitch
                    </td>

                </tr>
            @endforeach

        </table>
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
        </form>
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-9">
                {{ $personas->appends($data)->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $personas->total() }}</strong>


                <script>
                    document.getElementById('seleccionarTodos')?.addEventListener('click', function () {
                        document
                            .querySelectorAll('input[type=checkbox][name^="personas"]')
                            .forEach(cb => cb.checked = true);
                    });
                </script>

@endsection
