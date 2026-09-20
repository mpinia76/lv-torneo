@extends('layouts.app')

@section('pageTitle', 'Contención cruzada')

@section('content')
    <div class="container-fluid">
        <h1 class="display-6">Contención cruzada nombre / apellido</h1>
        <p>
            <a href="{{ route('jugadores.verificarPersonas') }}">&larr; Verificar personas</a>
        </p>

        @if ($errors->any())
            <div class="alert alert-danger">{{ implode(' ', $errors->all()) }}</div>
        @endif

        <div class="alert alert-light border small">
            Esto es una <b>simulación: no se guardó nada</b>.<br>
            Son los pares que hoy el sistema no arma y que aparecerían con la regla nueva del bloque D:
            <b>misma fecha de nacimiento exacta</b>, <b>ningún apellido en común</b> y
            <b>todos los tokens de una ficha adentro de la otra</b> sin importar en qué campo cayeron.
            Es el caso del nombre de pila cargado como apellido: "Luis&nbsp;/&nbsp;Enrique" contra
            "Luis&nbsp;Enrique&nbsp;/&nbsp;Martínez&nbsp;García".
        </div>

        <p class="small">
            La regla agrega <b>{{ $total }}</b> pares; <b>{{ $sobreUmbral }}</b> llegan al umbral {{ $umbral }}.
            @if($todos)
                · <a href="{{ route('personas.duplicados.contencion', ['umbral' => $umbral]) }}">ver solo los que llegan al umbral</a>
            @else
                · <a href="{{ route('personas.duplicados.contencion', ['umbral' => $umbral, 'todos' => 1]) }}">ver también los que quedan abajo</a>
            @endif
        </p>

        @if(!$pares)
            <p class="text-muted">No hay pares para mostrar.</p>
        @else
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>Pts</th>
                        <th>Ficha A</th>
                        <th>Ficha B</th>
                        <th>Nacimiento</th>
                        <th>Transfermarkt</th>
                        <th>Estado actual</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($pares as $p)
                    <tr class="@if($p['puntaje'] < $umbral) text-muted @endif">
                        <td><b>{{ $p['puntaje'] }}</b></td>
                        <td>
                            #{{ $p['a'] }} {{ $p['nombre_a'] }}
                            <span class="badge badge-secondary">{{ $p['roles_a'] ?: 'sin rol' }}</span>
                            @if($p['tm_a'])
                                <a href="{{ $p['tm_a'] }}" target="_blank" rel="noopener">TM</a>
                            @endif
                        </td>
                        <td>
                            #{{ $p['b'] }} {{ $p['nombre_b'] }}
                            <span class="badge badge-secondary">{{ $p['roles_b'] ?: 'sin rol' }}</span>
                            @if($p['tm_b'])
                                <a href="{{ $p['tm_b'] }}" target="_blank" rel="noopener">TM</a>
                            @endif
                        </td>
                        <td class="small">{{ $p['fecha'] }}</td>
                        <td class="small">
                            @if($p['tm_a'] && $p['tm_b'])
                                {{ $p['tm_a'] === $p['tm_b'] ? 'misma ficha' : 'distintas' }}
                            @elseif($p['tm_a'] || $p['tm_b'])
                                solo una
                            @else
                                sin TM
                            @endif
                        </td>
                        <td class="small">{{ $p['estado_actual'] ?: '-' }}</td>
                        <td class="small">{{ $p['motivo'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <form method="POST" action="{{ route('personas.duplicados.recalcular') }}"
                  onsubmit="return confirm('Se van a recalcular los pares candidatos. ¿Seguir?');">
                @csrf
                <input type="hidden" name="umbral" value="{{ $umbral }}">
                <button class="btn btn-primary">Aplicar: recalcular pares (sin reindexar)</button>
                <span class="small text-muted">
                    La regla no agrega tokens nuevos, así que no hace falta reconstruir el índice.
                </span>
            </form>
        @endif
    </div>
@endsection
