{{--
    Ficha compacta de una persona dentro de un par candidato.
    Espera: $p (Persona), $otro (Persona|null, para marcar diferencias),
            $info (array de FusionPersonas::peso), $sugerido (bool),
            $clubes (array de RegistrosPersonas::clubes para ESTA persona),
            $comunes (array [equipo => bool mismaTemporada] contra la otra ficha)
--}}
@php
    $info     = $info     ?? ['registros' => 0, 'campos' => 0, 'roles' => []];
    $sugerido = $sugerido ?? false;
    $clubes   = $clubes   ?? [];
    $comunes  = $comunes  ?? [];
    $nac      = $p->nacimiento ? \Carbon\Carbon::parse($p->nacimiento)->format('d/m/Y') : null;
    $nacOtro  = ($otro && $otro->nacimiento) ? \Carbon\Carbon::parse($otro->nacimiento)->format('d/m/Y') : null;
    $difNac   = $nac && $nacOtro && $nac !== $nacOtro;
    $vacia    = ($info['registros'] ?? 0) == 0;
    $tope     = \App\Services\RegistrosPersonas::MAX_CLUBES;

    // Link a la ficha pública: es la pantalla que muestra la carrera completa.
    $verUrl = null;
    if ($p->jugador) {
        $verUrl = route('jugadores.ver', ['jugadorId' => $p->jugador->id]);
    } elseif ($p->tecnico) {
        $verUrl = route('tecnicos.ver', ['tecnicoId' => $p->tecnico->id]);
    } elseif ($p->arbitro) {
        $verUrl = route('arbitros.ver', ['arbitroId' => $p->arbitro->id]);
    }
@endphp

<div class="dup-ficha @if($sugerido) dup-ficha-sugerida @endif @if($vacia) dup-ficha-vacia @endif">
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

                @if($vacia)
                    <span class="badge badge-danger" title="No figura en ningún partido, gol, plantel ni planilla">
                        sin registros
                    </span>
                @elseif($verUrl)
                    <a href="{{ $verUrl }}" target="_blank" class="badge badge-secondary"
                       title="Abrir la ficha completa: torneos, clubes y partidos">
                        ver {{ $info['registros'] }} reg.
                    </a>
                @else
                    <span class="badge badge-secondary">{{ $info['registros'] }} reg.</span>
                @endif
            </div>

            {{-- Clubes y temporadas: es lo que realmente decide el par. Dos fichas
                 de la misma persona comparten sí o sí algún plantel; dos homónimos
                 casi nunca. Los clubes que también están en la otra ficha se
                 resaltan, y en verde fuerte si además coinciden los años. --}}
            @if($clubes)
                <div class="small mt-1 dup-clubes">
                    @foreach(array_slice($clubes, 0, $tope) as $club)
                        @php
                            $esComun   = array_key_exists($club['equipo'], $comunes);
                            $mismoAnio = $esComun && $comunes[$club['equipo']];
                            $periodo   = $club['desde'] == $club['hasta'] ? $club['desde'] : $club['desde'].'-'.$club['hasta'];
                            $titulo    = $mismoAnio
                                ? 'La otra ficha también está en este club en las mismas temporadas: casi seguro es la misma persona'
                                : ($esComun ? 'La otra ficha también pasó por este club, pero en otros años' : '');
                        @endphp
                        <span class="dup-club @if($mismoAnio) dup-club-igual @elseif($esComun) dup-club-comun @endif"
                              @if($titulo) title="{{ $titulo }}" @endif>
                            {{ $club['equipo'] }} <span class="text-muted">{{ $periodo }}</span>
                        </span>
                    @endforeach
                    @if(count($clubes) > $tope)
                        <span class="text-muted">y {{ count($clubes) - $tope }} más</span>
                    @endif
                </div>
            @elseif(!$vacia)
                <div class="small mt-1 text-muted">registros sin club identificable</div>
            @endif

            <div class="small mt-1">
                @if($p->jugador)
                    <a href="{{ route('jugadores.edit', $p->jugador->id) }}" target="_blank">editar jugador</a>
                @elseif($p->tecnico)
                    <a href="{{ route('tecnicos.edit', $p->tecnico->id) }}" target="_blank">editar DT</a>
                @elseif($p->arbitro)
                    <a href="{{ route('arbitros.edit', $p->arbitro->id) }}" target="_blank">editar árbitro</a>
                @endif

                {{-- Una ficha sin registros no es candidata a fusión: no le aporta
                     nada a la que queda. Lo que corresponde es borrarla. --}}
                @if($vacia)
                    <form method="POST" action="{{ route('personas.eliminar') }}" class="d-inline ml-2"
                          {{-- El nombre NO va adentro del confirm: un apellido con apóstrofo
                               (D'Alessandro, O'Connor) rompe el string de JavaScript. --}}
                          onsubmit="return confirm('Se va a BORRAR la ficha #{{ $p->id }}, que no tiene ningún registro asociado. ¿Confirmás?')">
                        @csrf
                        <input type="hidden" name="personas[]" value="{{ $p->id }}">
                        <button class="btn btn-sm btn-outline-danger py-0">Eliminar esta ficha</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
