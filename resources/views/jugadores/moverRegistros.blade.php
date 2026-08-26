@extends('layouts.app')

@section('pageTitle', 'Mover registros')

@section('content')
    <style>
        .mv-ficha { border:1px solid #e3e6ea; border-radius:.35rem; padding:.5rem .75rem; height:100%; }
        .mv-origen { border-color:#dc3545; background:#fff5f5; }
        .mv-destino { border-color:#28a745; background:#f4fbf6; }
        .mv-flecha { font-size:2rem; line-height:1; color:#6c757d; text-align:center; }
        .mv-tabla td, .mv-tabla th { vertical-align: middle; font-size:.9rem; }
        .mv-detalle .badge { margin-right:.15rem; font-weight:500; }
        .mv-choca { background:#fff9e6; }
        /* partido atribuido al club por conjetura: no hay alineación que lo confirme */
        .mv-dudoso { background:#fff5f5; }
        .imgCircle { width:38px; height:38px; object-fit:cover; border-radius:50%; }
    </style>

    @php
        // Ojo con los nombres: $torneos, $grupo y $i están tomados a nivel global
        // por el layout (el footer y el select de torneos). Acá nada se llama así.
        $orig       = $previo['origen'];
        $dest       = $previo['destino'];
        $nombreClub = $etiqueta !== '' ? $etiqueta : implode(', ', $previo['equipos']);
        $totalFilas = 0;
        $dudosos    = 0;
        foreach ($previo['partidos'] as $unPartido) {
            $totalFilas += $unPartido['filas'];
            if ($unPartido['dudoso']) { $dudosos++; }
        }
    @endphp

    <div class="container-fluid">
        <h1 class="display-6">Mover registros de una ficha a la otra</h1>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <p class="text-muted">
            Las dos personas siguen existiendo: esto NO es una fusión. Se mueve el tramo
            <strong>{{ $nombreClub }}</strong> de una ficha a la otra, con sus planteles y sus partidos.
            La ficha de origen no se borra aunque quede sin registros.
        </p>

        <div class="row mb-3">
            <div class="col-md-5">
                <div class="mv-ficha mv-origen">
                    <div class="small text-muted">Sale de</div>
                    <strong>{{ $orig->apellido }}</strong>, {{ $orig->nombre }}
                    <span class="text-muted">#{{ $orig->id }}</span>
                    <div class="small">
                        @if($orig->nacimiento) {{ \Carbon\Carbon::parse($orig->nacimiento)->format('d/m/Y') }}
                        @else <span class="text-muted">sin fecha de nacimiento</span> @endif
                        · {{ $peso[$orig->id]['registros'] ?? 0 }} registros en total
                    </div>
                </div>
            </div>
            <div class="col-md-2 mv-flecha">&rarr;</div>
            <div class="col-md-5">
                <div class="mv-ficha mv-destino">
                    <div class="small text-muted">Entra en</div>
                    <strong>{{ $dest->apellido }}</strong>, {{ $dest->nombre }}
                    <span class="text-muted">#{{ $dest->id }}</span>
                    <div class="small">
                        @if($dest->nacimiento) {{ \Carbon\Carbon::parse($dest->nacimiento)->format('d/m/Y') }}
                        @else <span class="text-muted">sin fecha de nacimiento</span> @endif
                        · {{ $peso[$dest->id]['registros'] ?? 0 }} registros en total
                    </div>
                </div>
            </div>
        </div>

        @if($previo['crearFicha'])
            <div class="alert alert-warning">
                La persona #{{ $dest->id }} no tiene ficha de {{ $rol }}: se va a crear una.
                Se copian de la ficha de origen solo el puesto, el pie y el tipo de documento —describen
                el tramo que se está moviendo—; el resto de los campos obligatorios queda en blanco, para
                no arrastrar el documento ni los links de la otra persona.
                <strong>Revisá la ficha nueva después.</strong>
            </div>
        @endif

        @if(!$previo['plantillas'] && !$previo['partidos'])
            <div class="alert alert-danger">
                La ficha #{{ $orig->id }} no tiene ningún plantel ni partido de {{ $nombreClub }}.
                Puede que el club de la otra ficha sea otra fila de <code>equipos</code> con el mismo nombre:
                la pantalla de repetidos compara los clubes por nombre, no por id.
            </div>
        @endif

        <form method="POST" action="{{ route('personas.mover') }}"
              onsubmit="return confirm('Se van a mover los registros tildados de la ficha #{{ $orig->id }} a la #{{ $dest->id }}. Ninguna de las dos personas se borra. ¿Confirmás?')">
            @csrf
            <input type="hidden" name="origen"   value="{{ $orig->id }}">
            <input type="hidden" name="destino"  value="{{ $dest->id }}">
            <input type="hidden" name="rol"      value="{{ $rol }}">
            <input type="hidden" name="equipos"  value="{{ implode(',', array_keys($previo['equipos'])) }}">
            <input type="hidden" name="etiqueta" value="{{ $nombreClub }}">
            @if($volver)
                <input type="hidden" name="volver" value="{{ $volver }}">
            @endif

            {{-- ------------------------------------------------------------------
                 Planteles
            ------------------------------------------------------------------- --}}
            @if($previo['plantillas'])
                <h5>Planteles ({{ count($previo['plantillas']) }})</h5>
                <table class="table table-sm table-bordered mv-tabla">
                    <thead class="thead-light">
                        <tr>
                            <th style="width:36px;">
                                <input type="checkbox" class="mv-todos" data-grupo="pl" checked>
                            </th>
                            <th>Torneo</th>
                            <th>Club</th>
                            <th>Dorsal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($previo['plantillas'] as $unaPlantilla)
                            <tr class="@if($unaPlantilla['choca']) mv-choca @endif">
                                <td>
                                    <input type="checkbox" name="plantillas[]" class="mv-item-pl"
                                           value="{{ $unaPlantilla['id'] }}" checked>
                                </td>
                                <td>
                                    {{ $unaPlantilla['torneo'] }} {{ $unaPlantilla['year'] }}
                                    @if($unaPlantilla['grupo'])
                                        <span class="text-muted small">({{ $unaPlantilla['grupo'] }})</span>
                                    @endif
                                </td>
                                <td>{{ $unaPlantilla['equipo'] }}</td>
                                <td>{{ $unaPlantilla['dorsal'] }}</td>
                                <td>
                                    @if($unaPlantilla['choca'])
                                        <span class="badge badge-warning"
                                              title="La ficha de destino ya está en este plantel: las dos filas se unifican y se completan los campos vacíos">
                                            ya está en el destino
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- ------------------------------------------------------------------
                 Partidos
            ------------------------------------------------------------------- --}}
            @if($previo['partidos'])
                <h5>
                    Partidos ({{ count($previo['partidos']) }})
                    <small class="text-muted">— {{ $totalFilas }} registros: alineaciones, goles, tarjetas, cambios y penales</small>
                </h5>
                <p class="small text-muted mb-1">
                    Los goles y las tarjetas no saben de club: solo tienen partido y ficha. Por eso se mueven
                    por partido y no por temporada — si se movieran sueltos, el gol quedaría en una ficha que
                    ya no figura en ese partido.
                </p>
                @if($dudosos)
                    <div class="alert alert-warning py-2">
                        <strong>{{ $dudosos }} partidos vienen destildados.</strong>
                        En esos no hay alineación de esta ficha con el equipo cargado —o no hay alineación
                        en absoluto, solo el gol o la tarjeta—, así que el club sale del partido y no del
                        registro: que {{ $nombreClub }} esté jugando no prueba que haya jugado PARA
                        {{ $nombreClub }}, pudo jugar en contra. Tildalos solo si lo verificaste.
                    </div>
                @endif
                <table class="table table-sm table-bordered mv-tabla">
                    <thead class="thead-light">
                        <tr>
                            <th style="width:36px;">
                                {{-- Si hay partidos dudosos, el maestro NO viene tildado y al
                                     marcarlo tampoco los tilda: son la única protección de los
                                     partidos que no se pueden atribuir al club. --}}
                                <input type="checkbox" class="mv-todos" data-grupo="pa"
                                       @if(!$dudosos) checked @endif>
                            </th>
                            <th>Día</th>
                            <th>Torneo</th>
                            <th>Partido</th>
                            <th>Qué se mueve</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($previo['partidos'] as $unPartido)
                            <tr class="@if($unPartido['dudoso']) mv-dudoso @elseif($unPartido['choca']) mv-choca @endif">
                                <td>
                                    <input type="checkbox" name="partidos[]" class="mv-item-pa"
                                           value="{{ $unPartido['id'] }}"
                                           @if($unPartido['dudoso']) data-dudoso="1" @else checked @endif>
                                </td>
                                <td class="text-nowrap">
                                    {{ $unPartido['dia'] ? \Carbon\Carbon::parse($unPartido['dia'])->format('d/m/Y') : '' }}
                                </td>
                                <td>
                                    {{ $unPartido['torneo'] }} {{ $unPartido['year'] }}
                                    @if($unPartido['jornada'])
                                        <span class="text-muted small">· fecha {{ $unPartido['jornada'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $unPartido['local'] }} vs {{ $unPartido['visitante'] }}
                                    @if($unPartido['resultado'])
                                        <strong>{{ $unPartido['resultado'] }}</strong>
                                    @endif
                                </td>
                                <td class="mv-detalle">
                                    @foreach($unPartido['detalle'] as $unDato)
                                        <span class="badge badge-secondary">{{ $unDato }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    @if($unPartido['dudoso'])
                                        <span class="badge badge-danger"
                                              title="No hay alineación de esta ficha en este partido: no se puede confirmar para qué equipo jugó">
                                            sin alineación
                                        </span>
                                    @endif
                                    @if($unPartido['choca'])
                                        <span class="badge badge-warning"
                                              title="La ficha de destino ya figura en este partido: las filas repetidas se unifican">
                                            ya está en el destino
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            {{-- ------------------------------------------------------------------
                 Lo que NO se mueve, dicho antes y no después
            ------------------------------------------------------------------- --}}
            @if($previo['fueraDelClub'] || $previo['contraElClub'] || $previo['sinPartido'] || $previo['contradictorios'] || $previo['sinMover'])
                <div class="alert alert-info">
                    <strong>Se queda en la ficha #{{ $orig->id }}:</strong>
                    <ul class="mb-0">
                        @if($previo['contradictorios'])
                            <li>
                                <strong>{{ $previo['contradictorios'] }} partidos bloqueados:</strong>
                                ahí la ficha de destino ya juega para otro equipo. Una persona no puede
                                estar en las dos alineaciones del mismo partido, así que ese registro no
                                puede ser suyo — si igual creés que lo es, hay un error de carga antes que
                                un traspaso.
                            </li>
                        @endif
                        @if($previo['contraElClub'])
                            <li>
                                {{ $previo['contraElClub'] }} partidos donde esta ficha jugó
                                <em>contra</em> {{ $nombreClub }} con otro equipo. Sus goles y tarjetas no son de este tramo.
                            </li>
                        @endif
                        @if($previo['fueraDelClub'])
                            <li>{{ $previo['fueraDelClub'] }} partidos de otros clubes.</li>
                        @endif
                        @if($previo['sinPartido'])
                            <li>
                                {{ $previo['sinPartido'] }} filas con el partido sin cargar
                                (<code>partido_id</code> en NULL): no se pueden ubicar en ningún club.
                            </li>
                        @endif
                        @foreach($previo['sinMover'] as $unaTabla)
                            <li>
                                <code>{{ $unaTabla }}</code>: no cuelga de un partido ni de un plantel,
                                así que no hay forma de saber qué parte es de este tramo. Se revisa a mano.
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="form-group">
                {{-- El hidden va SIEMPRE: un checkbox sin tildar no manda nada y el
                     server no puede distinguir "destildado" de "no vino el campo". --}}
                <input type="hidden" name="descartar" value="0">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="descartar" value="1" id="descartar" checked>
                    <label class="form-check-label" for="descartar">
                        Marcar el par como <strong>personas distintas</strong> (recomendado: al mover el tramo,
                        el par pierde el club compartido, que era la única señal fuerte que tenía)
                    </label>
                </div>
            </div>

            <button class="btn btn-primary" @if(!$previo['plantillas'] && !$previo['partidos']) disabled @endif>
                Mover a la ficha #{{ $dest->id }}
            </button>
            <a href="{{ $volver ?: route('jugadores.verificarPersonas') }}" class="btn btn-link">Cancelar</a>
        </form>
    </div>

    <script>
        document.querySelectorAll('.mv-todos').forEach(function (maestro) {
            maestro.addEventListener('change', function () {
                document.querySelectorAll('.mv-item-' + maestro.dataset.grupo).forEach(function (uno) {
                    // Destildar es siempre seguro; tildar nunca alcanza a los
                    // partidos sin alineación, que van uno por uno y a mano.
                    if (!maestro.checked) { uno.checked = false; return; }
                    if (!uno.dataset.dudoso) { uno.checked = true; }
                });
            });
        });
    </script>
@endsection
