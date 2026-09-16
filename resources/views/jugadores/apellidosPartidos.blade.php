@extends('layouts.app')

@section('pageTitle', 'Apellidos partidos')

@section('content')
    <style>
        .ap-tabla td { vertical-align: middle; }
        .ap-viejo { color:#6c757d; text-decoration: line-through; }
        .ap-nuevo { font-weight:600; }
        .ap-cuenta { font-size:.78rem; color:#6c757d; white-space:nowrap; }
        .imgCircle { width:32px; height:32px; object-fit:cover; border-radius:50%; }
    </style>

    <div class="container-fluid">
        <h1 class="display-6">Árbitros con el apellido partido</h1>
        <p>
            <a href="{{ route('jugadores.verificarPersonas') }}">&larr; Verificar personas</a>
        </p>

        @if (\Session::has('success'))
            <div class="alert alert-success">{{ \Session::get('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ implode(' ', $errors->all()) }}</div>
        @endif

        <div class="alert alert-light border small">
            Árbitros de países con dos apellidos que quedaron con <b>un solo apellido y dos o más nombres</b>
            ("Leandro Carbajales | Gómez"). Se propone pasar la última palabra del nombre al apellido.
            La columna <b>Base</b> dice en cuántas <i>otras</i> fichas esa palabra aparece como apellido / como nombre.
            <br>
            <span class="badge badge-success">apellido</span> la base la tiene como apellido ·
            <span class="badge badge-info">probable</span> nadie la tiene como nombre de pila ·
            <span class="badge badge-warning">dudoso</span> aparece como las dos cosas ·
            <span class="badge badge-secondary">forma</span> tres nombres o más, un apellido.
            Solo vienen tildados los dos primeros; los otros, mirarlos antes.
        </div>

        <p class="small">
            Origen:
            @if($soloTm)
                <b>cargados desde Transfermarkt</b> · <a href="{{ route('personas.apellidos', ['origen' => 'todos', 'ver' => $conNombres ? 'nombres' : null]) }}">todos los árbitros</a>
            @else
                <a href="{{ route('personas.apellidos', ['ver' => $conNombres ? 'nombres' : null]) }}">cargados desde Transfermarkt</a> · <b>todos los árbitros</b>
            @endif
            &nbsp;|&nbsp;
            @if($conNombres)
                <a href="{{ route('personas.apellidos', ['origen' => $soloTm ? null : 'todos']) }}">ocultar los que parecen bien</a>
            @else
                <a href="{{ route('personas.apellidos', ['origen' => $soloTm ? null : 'todos', 'ver' => 'nombres']) }}">mostrar también los que parecen bien</a>
            @endif
            &nbsp;|&nbsp;
            @foreach($resumen as $v => $n) {{ $v }}: <b>{{ $n }}</b> @endforeach
        </p>

        @if(!$filas)
            <p class="text-muted">No hay nada para corregir.</p>
        @else
        <form method="POST" action="{{ route('personas.apellidos.aplicar') }}">
            @csrf
            <p>
                <label class="mr-3"><input type="checkbox" id="ap-todos"> tildar todo lo visible</label>
                <button type="submit" class="btn btn-primary btn-sm">Corregir los tildados</button>
            </p>

            <table class="table table-sm ap-tabla">
                <thead>
                <tr>
                    <th></th><th></th><th>Id</th><th>Nacionalidad</th>
                    <th>Ahora (apellido, nombre)</th><th>Queda</th><th>Mostrar</th>
                    <th>Base</th><th>Veredicto</th><th></th>
                </tr>
                </thead>
                <tbody>
                @foreach($filas as $f)
                    @php
                        $color = ['apellido' => 'success', 'probable' => 'info', 'dudoso' => 'warning',
                                  'forma' => 'secondary', 'nombre' => 'light'][$f['veredicto']];
                    @endphp
                    <tr>
                        <td>
                            <input type="checkbox" class="ap-check" name="ids[]" value="{{ $f['id'] }}" @if($f['tildado']) checked @endif>
                            <input type="hidden" name="antes[{{ $f['id'] }}]" value="{{ $f['apellido'] }}|{{ $f['nombre'] }}">
                        </td>
                        <td><img class="imgCircle" src="{{ url('images/'.($f['foto'] ?: 'sin_foto.png')) }}" alt=""></td>
                        <td>{{ $f['id'] }}</td>
                        <td>{{ $f['nacionalidad'] }}</td>
                        <td class="ap-viejo">{{ $f['apellido'] }}, {{ $f['nombre'] }}</td>
                        <td class="ap-nuevo">{{ $f['nuevo_apellido'] }}, {{ $f['nuevo_nombre'] }}</td>
                        <td>
                            {{ $f['name'] }}
                            @if($f['nuevo_name'] !== $f['name']) &rarr; <b>{{ $f['nuevo_name'] }}</b> @endif
                        </td>
                        <td class="ap-cuenta">
                            @if($f['veredicto'] === 'forma') &mdash;
                            @else «{{ $f['palabra'] }}»: {{ $f['como_apellido'] }} ap / {{ $f['como_nombre'] }} nom
                            @endif
                        </td>
                        <td><span class="badge badge-{{ $color }}">{{ $f['veredicto'] }}</span></td>
                        <td><a href="{{ route('arbitros.edit', $f['arbitro_id']) }}" target="_blank" class="small">editar</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <button type="submit" class="btn btn-primary btn-sm">Corregir los tildados</button>
        </form>
        @endif
    </div>

    <script>
        document.getElementById('ap-todos') && document.getElementById('ap-todos').addEventListener('change', function () {
            var on = this.checked;
            document.querySelectorAll('.ap-check').forEach(function (c) { c.checked = on; });
        });
    </script>
@endsection
