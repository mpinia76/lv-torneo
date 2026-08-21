{{--
    Ficha compacta de una persona dentro de un par candidato.
    Espera: $p (Persona), $otro (Persona|null, para marcar diferencias),
            $info (array de FusionPersonas::peso), $sugerido (bool)
--}}
@php
    $info     = $info     ?? ['registros' => 0, 'campos' => 0, 'roles' => []];
    $sugerido = $sugerido ?? false;
    $nac      = $p->nacimiento ? \Carbon\Carbon::parse($p->nacimiento)->format('d/m/Y') : null;
    $nacOtro  = ($otro && $otro->nacimiento) ? \Carbon\Carbon::parse($otro->nacimiento)->format('d/m/Y') : null;
    $difNac   = $nac && $nacOtro && $nac !== $nacOtro;
@endphp

<div class="dup-ficha @if($sugerido) dup-ficha-sugerida @endif">
    <div class="d-flex" style="gap:.5rem;">
        <div>
            @if($p->foto)
                <img class="imgCircle" src="{{ url('images/'.$p->foto) }}" alt="">
            @elseif($p->tecnico)
                <img class="imgCircle" src="{{ url('images/sin_foto_tecnico.png') }}" alt="">
            @elseif($p->arbitro)
                <img class="imgCircle" src="{{ url('images/sin_foto_arbitro.png') }}" alt="">
            @else
                <img class="imgCircle" src="{{ url('images/sin_foto.png') }}" alt="">
            @endif
        </div>
        <div style="min-width:0;">
            <div>
                <strong>{{ $p->apellido }}</strong>, {{ $p->nombre }}
                <img src="{{ $p->bandera_url }}" alt="{{ $p->nacionalidad }}" title="{{ $p->nacionalidad }}">
            </div>
            <div class="text-muted small">
                #{{ $p->id }}
                @if($p->name) · {{ $p->name }} @endif
            </div>
            <div class="small">
                @if($nac)
                    <span class="@if($difNac) text-danger font-weight-bold @endif">{{ $nac }}</span>
                @else
                    <span class="text-muted">sin fecha de nacimiento</span>
                @endif
                @if($p->fallecimiento)
                    † {{ \Carbon\Carbon::parse($p->fallecimiento)->format('d/m/Y') }}
                @endif
            </div>
            <div class="small mt-1">
                @if($p->jugador)
                    <span class="badge badge-primary">Jugador {{ $p->jugador->id }}</span>
                    @if($p->jugador->tipoJugador)<span class="badge badge-light">{{ $p->jugador->tipoJugador }}</span>@endif
                @endif
                @if($p->tecnico)
                    <span class="badge badge-success">DT {{ $p->tecnico->id }}</span>
                @endif
                @if($p->arbitro)
                    <span class="badge badge-warning">Árbitro {{ $p->arbitro->id }}</span>
                @endif
                @if(!$p->jugador && !$p->tecnico && !$p->arbitro)
                    <span class="badge badge-danger">sin rol</span>
                @endif
                <span class="badge badge-secondary" title="Partidos, goles y plantillas asociados">
                    {{ $info['registros'] }} reg.
                </span>
            </div>
            <div class="small mt-1">
                @if($p->jugador)
                    <a href="{{ route('jugadores.edit', $p->jugador->id) }}" target="_blank">editar jugador</a>
                @elseif($p->tecnico)
                    <a href="{{ route('tecnicos.edit', $p->tecnico->id) }}" target="_blank">editar DT</a>
                @elseif($p->arbitro)
                    <a href="{{ route('arbitros.edit', $p->arbitro->id) }}" target="_blank">editar árbitro</a>
                @endif
            </div>
        </div>
    </div>
</div>
