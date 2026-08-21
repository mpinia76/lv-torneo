{{-- Fila simple de persona para las pestañas "sin nombre/apellido" y "nacionalidad". --}}
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
