{{--
    Fila simple de persona para las pestañas "sin nombre/apellido" y "nacionalidad".
    Espera: $p (Persona) y, opcionales, $info (FusionPersonas::peso de esta
    persona) y $clubes (RegistrosPersonas::clubes). Con eso muestra el mismo
    "ver N reg." que la pestaña de repetidos: el link a la ficha pública, que es
    donde se ve la carrera entera.
--}}
@php
    $info   = $info   ?? null;
    $clubes = $clubes ?? [];
    $tope   = \App\Services\RegistrosPersonas::MAX_CLUBES;

    $verUrl = null;
    if ($p->jugador) {
        $verUrl = route('jugadores.ver', ['jugadorId' => $p->jugador->id]);
    } elseif ($p->tecnico) {
        $verUrl = route('tecnicos.ver', ['tecnicoId' => $p->tecnico->id]);
    } elseif ($p->arbitro) {
        $verUrl = route('arbitros.ver', ['arbitroId' => $p->arbitro->id]);
    }
@endphp
<tr>
    <td>
        @if($p->foto)
            <img class="imgCircle" src="{{ url('images/'.$p->foto) }}" alt="">
        @elseif($p->tecnico)
            <img class="imgCircle" src="{{ url('images/sin_foto_tecnico.png') }}" alt="">
        @elseif($p->arbitro)
            <img class="imgCircle" src="{{ url('images/sin_foto_arbitro.png') }}" alt="">
        @else
            <img class="imgCircle" src="{{ url('images/sin_foto.png') }}" alt="">
        @endif
        <img src="{{ $p->bandera_url }}" alt="{{ $p->nacionalidad }}">
    </td>
    <td>{{ $p->id }}</td>
    <td>{{ $p->name }}</td>
    <td>{{ $p->apellido }}</td>
    <td>{{ $p->nombre }}</td>
    <td>{{ $p->nacionalidad }}</td>
    <td>{{ $p->nacimiento ? $p->getAgeWithDateAttribute() : '' }}</td>
    <td>{{ optional($p->jugador)->id }}</td>
    <td>{{ optional($p->tecnico)->id }}</td>
    <td>{{ optional($p->arbitro)->id }}</td>
    <td class="small">
        @if($info !== null)
            @if(($info['registros'] ?? 0) == 0)
                <span class="badge badge-danger" title="No figura en ningún partido, gol, plantel ni planilla">sin registros</span>
            @elseif($verUrl)
                <a href="{{ $verUrl }}" target="_blank" class="badge badge-secondary"
                   title="Abrir la ficha completa: torneos, clubes y partidos">ver {{ $info['registros'] }} reg.</a>
            @else
                <span class="badge badge-secondary">{{ $info['registros'] }} reg.</span>
            @endif
        @endif
        @if($clubes)
            <div class="dup-clubes mt-1">
                @foreach(array_slice($clubes, 0, $tope) as $club)
                    <span class="dup-club">{{ $club['equipo'] }}
                        <span class="text-muted">{{ $club['desde'] == $club['hasta'] ? $club['desde'] : $club['desde'].'-'.$club['hasta'] }}</span></span>
                @endforeach
                @if(count($clubes) > $tope)
                    <span class="text-muted">y {{ count($clubes) - $tope }} más</span>
                @endif
            </div>
        @endif
    </td>
    <td>
        <div class="d-flex" style="align-items:center;">
            @if($p->jugador)
                <a href="{{ route('jugadores.edit', $p->jugador->id) }}" class="btn btn-sm btn-primary m-1">Editar</a>
                <a href="{{ route('jugadores.reasignar', $p->jugador->id) }}" class="btn btn-sm btn-info m-1">Reasignar</a>
            @elseif($p->tecnico)
                <a href="{{ route('tecnicos.edit', $p->tecnico->id) }}" class="btn btn-sm btn-primary m-1">Editar</a>
            @elseif($p->arbitro)
                <a href="{{ route('arbitros.edit', $p->arbitro->id) }}" class="btn btn-sm btn-primary m-1">Editar</a>
            @endif
        </div>
    </td>
</tr>
