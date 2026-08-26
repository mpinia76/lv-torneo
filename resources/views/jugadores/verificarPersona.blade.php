@extends('layouts.app')

@section('pageTitle', 'Verificar personas')

@section('content')
    <style>
        .dup-ficha { border:1px solid #e3e6ea; border-radius:.35rem; padding:.5rem; height:100%; }
        .dup-ficha-sugerida { border-color:#28a745; background:#f4fbf6; }
        .dup-tabla td { vertical-align: middle; }
        .dup-puntaje { font-size:1rem; }
        .dup-motivo { font-size:.78rem; color:#6c757d; display:block; max-width:170px; }
        .imgCircle { width:38px; height:38px; object-fit:cover; border-radius:50%; }
        .dup-barra { background:#f8f9fa; border:1px solid #e3e6ea; border-radius:.35rem; padding:.75rem; margin-bottom:1rem; }
        .nav-tabs .nav-link.active { background-color:#007bff; color:#fff; border-color:#007bff; }
        .dup-ficha-vacia { border-color:#dc3545; background:#fff5f5; }
        .dup-clubes { line-height:1.7; }
        .dup-club { display:inline-block; border:1px solid #e3e6ea; border-radius:.25rem;
                    padding:0 .3rem; margin:0 .15rem .15rem 0; background:#fff; white-space:nowrap; }
        /* mismo club en las dos fichas, pero en años que no se pisan */
        .dup-club-comun { border-color:#ffc107; background:#fff9e6; }
        /* mismo club Y años solapados: la señal fuerte de que es la misma persona */
        .dup-club-igual { border-color:#28a745; background:#e9f7ee; font-weight:600; }
    </style>

    <div class="container-fluid">
        <h1 class="display-6">Verificar personas</h1>

        {{-- Los mensajes van escapados: pueden contener el texto de una excepción
             de MySQL, y ahí adentro viajan nombres cargados por el usuario. --}}
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (\Session::has('success'))
            <div class="alert alert-success">{{ \Session::get('success') }}</div>
        @endif

        @if(!$indexado)
            <div class="alert alert-warning">
                Todavía hay personas sin indexar. Apretá <strong>Recalcular</strong> acá abajo, o corré
                <code>php artisan personas:duplicados</code> desde la consola (es lo recomendado para la primera pasada
                sobre toda la base, así no depende del tiempo máximo del navegador).
            </div>
        @endif

        {{-- ------------------------------------------------------------------
             Barra de filtros y de recálculo
        ------------------------------------------------------------------- --}}
        <div class="dup-barra">
            <div class="row">
                <div class="col-md-8">
                    <form method="GET" action="{{ route('jugadores.verificarPersonas') }}" class="form-inline">
                        <input type="hidden" name="tab" value="{{ $tab }}">

                        <label class="mr-1">Estado</label>
                        <select name="estado" class="form-control form-control-sm mr-2">
                            <option value="pendiente"  @if($estado=='pendiente') selected @endif>Pendientes</option>
                            <option value="descartado" @if($estado=='descartado') selected @endif>Descartados</option>
                            <option value="todos"      @if($estado=='todos') selected @endif>Todos</option>
                        </select>

                        <label class="mr-1">Puntaje mínimo</label>
                        <input type="number" name="umbral" min="1" max="100" value="{{ $umbral }}"
                               class="form-control form-control-sm mr-2" style="width:80px;">

                        <input type="text" name="q" value="{{ $buscar }}" placeholder="buscar apellido..."
                               class="form-control form-control-sm mr-2">

                        <button class="btn btn-sm btn-primary">Filtrar</button>
                    </form>
                    <small class="text-muted">
                        100 = nombre idéntico. Por debajo de 70 empiezan a aparecer homónimos que no son la misma persona.
                    </small>
                </div>

                <div class="col-md-4 text-right">
                    <form method="POST" action="{{ route('personas.duplicados.recalcular') }}"
                          onsubmit="return confirm('Recalcular puede tardar un rato. ¿Seguimos?')">
                        @csrf
                        <input type="hidden" name="umbral" value="{{ $umbral }}">
                        <input type="hidden" name="reindexar" value="0">
                        <label class="small mr-2" title="Recalcula las claves y los tokens de todas las personas. Sacale el tilde si solo cambiaste el umbral.">
                            <input type="checkbox" name="reindexar" value="1" checked> reconstruir índice
                        </label>
                        <button class="btn btn-sm btn-dark">Recalcular</button>
                    </form>
                    <small class="text-muted d-block mt-1">{{ $conteos['fusiones'] }} fusiones hechas hasta ahora</small>
                </div>
            </div>
        </div>

        {{-- ------------------------------------------------------------------
             Pestañas (son enlaces: cada una carga solo sus propios datos)
        ------------------------------------------------------------------- --}}
        @php
            $qs = ['estado' => $estado, 'umbral' => $umbral, 'q' => $buscar];
        @endphp
        @php
            $tabsAparte = ['sin-nombre', 'nacionalidad', 'sin-registros', 'sin-fecha'];
        @endphp
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <a class="nav-link @if(!in_array($tab, $tabsAparte)) active @endif"
                   href="{{ route('jugadores.verificarPersonas', $qs + ['tab' => 'repetidos']) }}">
                    Posibles repetidos <span class="badge badge-light">{{ $conteos['pendiente'] }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link @if($tab=='sin-registros') active @endif"
                   href="{{ route('jugadores.verificarPersonas', $qs + ['tab' => 'sin-registros']) }}">
                    Sin registros <span class="badge badge-light">{{ $conteos['sinRegistros'] }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link @if($tab=='sin-fecha') active @endif"
                   href="{{ route('jugadores.verificarPersonas', $qs + ['tab' => 'sin-fecha']) }}">
                    Sin fecha de nacimiento <span class="badge badge-light">{{ $conteos['sinFecha'] }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link @if($tab=='sin-nombre') active @endif"
                   href="{{ route('jugadores.verificarPersonas', $qs + ['tab' => 'sin-nombre']) }}">
                    Sin nombre/apellido <span class="badge badge-light">{{ $conteos['sinNombre'] }}</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link @if($tab=='nacionalidad') active @endif"
                   href="{{ route('jugadores.verificarPersonas', $qs + ['tab' => 'nacionalidad']) }}">
                    Problema en nacionalidad <span class="badge badge-light">{{ $conteos['sinBandera'] }}</span>
                </a>
            </li>
        </ul>

        {{-- ================================================================
             Pestaña 1: pares repetidos
        ================================================================= --}}
        @if(!in_array($tab, $tabsAparte))

            @if($pares->total() == 0)
                <div class="alert alert-info">
                    No hay pares para mostrar con este filtro.
                    @if($conteos['pendiente'] == 0 && $conteos['descartado'] == 0)
                        Todavía no se calcularon los candidatos: apretá <strong>Recalcular</strong>.
                    @endif
                </div>
            @else
                {{-- El formulario de lote va suelto y las casillas de la tabla lo
                     referencian con form="formLote": así cada fila puede tener sus
                     propios formularios sin anidarlos (HTML no lo permite). --}}
                <form id="formLote" method="POST" action="{{ route('personas.duplicados.lote') }}"
                      onsubmit="return confirmarLote(this, event)"></form>
                <input type="hidden" name="_token" value="{{ csrf_token() }}" form="formLote">

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <label class="small mb-0"><input type="checkbox" id="tildarTodos"> tildar todo lo visible</label>
                    </div>
                    <div>
                        <button form="formLote" name="accion" value="descartar" class="btn btn-sm btn-outline-secondary"
                                onclick="window.__accionLote='descartar'">
                            Marcar los tildados como personas distintas
                        </button>
                        <button form="formLote" name="accion" value="fusionar" class="btn btn-sm btn-outline-danger"
                                onclick="window.__accionLote='fusionar'">
                            Fusionar los tildados (queda el sugerido)
                        </button>
                    </div>
                </div>

                <table class="table table-sm dup-tabla">
                    <thead>
                        <tr>
                            <th style="width:28px;"></th>
                            <th style="width:180px;">Coincidencia</th>
                            <th>Persona A</th>
                            <th>Persona B</th>
                            <th style="width:230px;">Qué hago</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($pares as $par)
                        @php
                            $a = $personas->get($par->persona_id);
                            $b = $personas->get($par->simil_id);
                        @endphp
                        @continue(!$a || !$b)
                        @php
                            $pesoA = $peso[$par->persona_id] ?? ['registros'=>0,'campos'=>0,'roles'=>[]];
                            $pesoB = $peso[$par->simil_id]  ?? ['registros'=>0,'campos'=>0,'roles'=>[]];
                            $ganadorSugerido = \App\Http\Controllers\PersonaDuplicadoController::sugerirGanador(
                                $par->persona_id, $par->simil_id, $peso
                            );
                            $colorPuntaje = $par->puntaje >= 95 ? 'danger' : ($par->puntaje >= 80 ? 'warning' : 'secondary');

                            $clubesA = $clubes[$par->persona_id] ?? [];
                            $clubesB = $clubes[$par->simil_id]   ?? [];
                            // Clubes compartidos, calculado una vez por par.
                            $comunesA = \App\Services\RegistrosPersonas::enComun($clubesA, $clubesB);
                            $comunesB = \App\Services\RegistrosPersonas::enComun($clubesB, $clubesA);
                            $hayClubeSolapado = in_array(true, $comunesA, true);
                        @endphp
                        <tr>
                            <td>
                                <input type="checkbox" name="pares[]" value="{{ $par->id }}" form="formLote" class="tildable">
                            </td>
                            <td>
                                <span class="badge badge-{{ $colorPuntaje }} dup-puntaje">{{ $par->puntaje }}</span>
                                @if($par->estado == 'descartado')
                                    <span class="badge badge-light">descartado</span>
                                @endif
                                <span class="dup-motivo">{{ $par->motivo }}</span>
                                @if($hayClubeSolapado)
                                    <span class="badge badge-success" title="Las dos fichas comparten club en las mismas temporadas">
                                        mismo club y año
                                    </span>
                                @endif
                            </td>
                            <td style="width:30%;">
                                @include('jugadores._personaCelda', [
                                    'p' => $a, 'otro' => $b, 'info' => $pesoA,
                                    'sugerido' => $ganadorSugerido == $par->persona_id,
                                    'clubes' => $clubesA, 'comunes' => $comunesA,
                                ])
                            </td>
                            <td style="width:30%;">
                                @include('jugadores._personaCelda', [
                                    'p' => $b, 'otro' => $a, 'info' => $pesoB,
                                    'sugerido' => $ganadorSugerido == $par->simil_id,
                                    'clubes' => $clubesB, 'comunes' => $comunesB,
                                ])
                            </td>
                            <td>
                                <form method="POST" action="{{ route('personas.duplicados.fusionar') }}" class="mb-1"
                                      onsubmit="return confirm('Se va a conservar la persona #{{ $par->persona_id }} y se borra la #{{ $par->simil_id }}, moviéndole todos los partidos, goles y planteles. ¿Confirmás?')">
                                    @csrf
                                    <input type="hidden" name="ganador_id"  value="{{ $par->persona_id }}">
                                    <input type="hidden" name="perdedor_id" value="{{ $par->simil_id }}">
                                    <button class="btn btn-sm btn-danger btn-block">
                                        Quedarme con A (#{{ $par->persona_id }})
                                        @if($ganadorSugerido == $par->persona_id) ★ @endif
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('personas.duplicados.fusionar') }}" class="mb-1"
                                      onsubmit="return confirm('Se va a conservar la persona #{{ $par->simil_id }} y se borra la #{{ $par->persona_id }}, moviéndole todos los partidos, goles y planteles. ¿Confirmás?')">
                                    @csrf
                                    <input type="hidden" name="ganador_id"  value="{{ $par->simil_id }}">
                                    <input type="hidden" name="perdedor_id" value="{{ $par->persona_id }}">
                                    <button class="btn btn-sm btn-danger btn-block">
                                        Quedarme con B (#{{ $par->simil_id }})
                                        @if($ganadorSugerido == $par->simil_id) ★ @endif
                                    </button>
                                </form>

                                @if($par->estado == 'descartado')
                                    <form method="POST" action="{{ route('personas.duplicados.reabrir') }}">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $par->id }}">
                                        <button class="btn btn-sm btn-outline-secondary btn-block">Volver a pendientes</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('personas.duplicados.descartar') }}">
                                        @csrf
                                        <input type="hidden" name="persona_id" value="{{ $par->persona_id }}">
                                        <input type="hidden" name="simil_id"   value="{{ $par->simil_id }}">
                                        <button class="btn btn-sm btn-success btn-block">Son personas distintas</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <div class="row">
                    <div class="col-md-9">{{ $pares->links() }}</div>
                    <div class="col-md-3 text-right"><strong>Total: {{ $pares->total() }} pares</strong></div>
                </div>
            @endif
        @endif

        {{-- ================================================================
             Pestaña 2: sin nombre / apellido
        ================================================================= --}}
        @if($tab == 'sin-nombre')
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th></th><th>Id</th><th>Mostrar</th><th>Apellido</th><th>Nombre</th>
                        <th>Nacionalidad</th><th>Edad</th><th>Jugador</th><th>Técnico</th><th>Árbitro</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($sinNombre as $p)
                    @include('jugadores._personaFila', ['p' => $p])
                @endforeach
                </tbody>
            </table>
            <div class="row">
                <div class="col-md-9">{{ $sinNombre->links() }}</div>
                <div class="col-md-3 text-right"><strong>Total: {{ $sinNombre->total() }}</strong></div>
            </div>
        @endif

        {{-- ================================================================
             Pestaña 3: nacionalidad sin bandera
        ================================================================= --}}
        @if($tab == 'nacionalidad')
            <p class="text-muted small">
                Son las personas cuya nacionalidad no tiene un <code>.gif</code> en <code>public/images</code>.
                Suele ser un error de tipeo en la nacionalidad, o una bandera que falta subir.
            </p>
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th></th><th>Id</th><th>Mostrar</th><th>Apellido</th><th>Nombre</th>
                        <th>Nacionalidad</th><th>Edad</th><th>Jugador</th><th>Técnico</th><th>Árbitro</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @foreach($sinBandera as $p)
                    @include('jugadores._personaFila', ['p' => $p])
                @endforeach
                </tbody>
            </table>
            <div class="row">
                <div class="col-md-9">{{ $sinBandera->links() }}</div>
                <div class="col-md-3 text-right"><strong>Total: {{ $sinBandera->total() }}</strong></div>
            </div>
        @endif

        {{-- ================================================================
             Pestaña 4: personas sin ningún registro
        ================================================================= --}}
        @if($tab == 'sin-registros')
            <p class="text-muted small">
                Personas que no figuran en ningún partido, gol, plantel ni planilla: son fichas que quedaron
                de una importación y no las usa nada. <strong>No son candidatas a fusión</strong> —no le aportan
                nada a la ficha que quedaría— así que lo que corresponde es borrarlas.
                Antes de borrar, el sistema vuelve a contar los registros de cada una: si alguna dejó de estar
                vacía, se saltea y avisa.
            </p>

            @if($sinRegistros->total() == 0)
                <div class="alert alert-info">No hay personas sin registros. Todo limpio.</div>
            @else
                <form id="formBorrar" method="POST" action="{{ route('personas.eliminar') }}"
                      onsubmit="return confirmarBorrado()"></form>
                <input type="hidden" name="_token" value="{{ csrf_token() }}" form="formBorrar">

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="small mb-0"><input type="checkbox" id="tildarTodos"> tildar todo lo visible</label>
                    <button form="formBorrar" class="btn btn-sm btn-danger">Eliminar las tildadas</button>
                </div>

                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th style="width:28px;"></th>
                            <th></th><th>Id</th><th>Mostrar</th><th>Apellido</th><th>Nombre</th>
                            <th>Nacionalidad</th><th>Nacimiento</th><th>Roles</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($sinRegistros as $huerfana)
                        <tr>
                            <td>
                                <input type="checkbox" name="personas[]" value="{{ $huerfana->id }}"
                                       form="formBorrar" class="tildable">
                            </td>
                            <td>
                                @if($huerfana->foto)
                                    <img class="imgCircle" src="{{ url('images/'.$huerfana->foto) }}" alt="">
                                @else
                                    <img class="imgCircle" src="{{ url('images/sin_foto.png') }}" alt="">
                                @endif
                                <img src="{{ $huerfana->bandera_url }}" alt="{{ $huerfana->nacionalidad }}">
                            </td>
                            <td>{{ $huerfana->id }}</td>
                            <td>{{ $huerfana->name }}</td>
                            <td>{{ $huerfana->apellido }}</td>
                            <td>{{ $huerfana->nombre }}</td>
                            <td>{{ $huerfana->nacionalidad }}</td>
                            <td>{{ $huerfana->nacimiento ? \Carbon\Carbon::parse($huerfana->nacimiento)->format('d/m/Y') : '' }}</td>
                            <td>
                                @if($huerfana->jugador)<span class="badge badge-primary">Jugador {{ $huerfana->jugador->id }}</span>@endif
                                @if($huerfana->tecnico)<span class="badge badge-success">DT {{ $huerfana->tecnico->id }}</span>@endif
                                @if($huerfana->arbitro)<span class="badge badge-warning">Árbitro {{ $huerfana->arbitro->id }}</span>@endif
                                @if(!$huerfana->jugador && !$huerfana->tecnico && !$huerfana->arbitro)
                                    <span class="badge badge-danger">sin rol</span>
                                @endif
                            </td>
                            <td>
                                @if($huerfana->jugador)
                                    <a href="{{ route('jugadores.edit', $huerfana->jugador->id) }}" target="_blank" class="small">editar</a>
                                @elseif($huerfana->tecnico)
                                    <a href="{{ route('tecnicos.edit', $huerfana->tecnico->id) }}" target="_blank" class="small">editar</a>
                                @elseif($huerfana->arbitro)
                                    <a href="{{ route('arbitros.edit', $huerfana->arbitro->id) }}" target="_blank" class="small">editar</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <div class="row">
                    <div class="col-md-9">{{ $sinRegistros->links() }}</div>
                    <div class="col-md-3 text-right"><strong>Total: {{ $sinRegistros->total() }}</strong></div>
                </div>
            @endif
        @endif

        {{-- ================================================================
             Pestaña 5: personas sin fecha de nacimiento
        ================================================================= --}}
        @if($tab == 'sin-fecha')
            <p class="text-muted small">
                La fecha de nacimiento es el desempate de la pantalla de repetidos: sin ella, un par no suma
                el +12 de "misma fecha" ni se lleva la resta de "fechas distintas", y queda flotando.
                <strong>Cada fecha que se completa es un par menos para mirar a mano.</strong>
                El botón baja los perfiles de Transfermarkt (una llamada cada 50 personas) y escribe
                <strong>solo los campos vacíos</strong>: nunca pisa lo que ya está cargado, porque TM manda
                fechas mal seguido y lo revisado a mano vale más.
            </p>
            <p class="text-muted small">
                Hay personas cuya fecha no está en ninguna parte —árbitros viejos, sobre todo— y esas iban a
                quedar en la lista para siempre. Cuando busques una y no aparezca, marcala como
                <strong>sin fecha conocida</strong>: sale de la cola y del contador, queda anotado quién lo
                decidió y por qué, y se puede volver a abrir cuando quieras. <strong>No borra ni inventa
                nada</strong>: la ficha sigue sin fecha, lo único que cambia es que dejamos de buscarla.
            </p>

            @php $verDesc = request('ver') === 'desconocidas'; @endphp

            @if($conteos['sinFecha'] == 0 && $conteos['sinFechaDesc'] == 0)
                <div class="alert alert-info">No hay personas sin fecha de nacimiento.</div>
            @else
                @php $det = $conteos['sinFechaDet']; @endphp
                <table class="table table-sm table-bordered w-auto mb-3">
                    <thead class="thead-light">
                        <tr>
                            <th>Rol</th><th class="text-right">En la cola</th>
                            <th class="text-right">Con id de TM</th>
                            <th class="text-right">Sin id de TM</th>
                            <th class="text-right">Ya consultadas</th>
                            <th class="text-right">Sin fecha conocida</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach(['jugador' => 'Jugadores', 'tecnico' => 'DTs', 'arbitro' => 'Árbitros'] as $k => $etiqueta)
                        <tr>
                            <td>{{ $etiqueta }}</td>
                            <td class="text-right">{{ $det[$k]['total'] }}</td>
                            <td class="text-right">{{ $det[$k]['con_tm'] }}</td>
                            <td class="text-right">{{ $det[$k]['sin_tm'] }}</td>
                            <td class="text-right text-muted">{{ $det[$k]['agotadas'] }}</td>
                            <td class="text-right text-muted">{{ $det[$k]['descartadas'] }}</td>
                        </tr>
                    @endforeach
                        <tr class="font-weight-bold">
                            <td>Total</td>
                            <td class="text-right">{{ $det['total']['total'] }}</td>
                            <td class="text-right">{{ $det['total']['con_tm'] }}</td>
                            <td class="text-right">{{ $det['total']['sin_tm'] }}</td>
                            <td class="text-right">{{ $det['total']['agotadas'] }}</td>
                            <td class="text-right">{{ $det['total']['descartadas'] }}</td>
                        </tr>
                    </tbody>
                </table>

                <ul class="nav nav-pills mb-3 small">
                    <li class="nav-item">
                        <a class="nav-link py-1 @if(!$verDesc) active @endif"
                           href="{{ route('jugadores.verificarPersonas', $qs + ['tab' => 'sin-fecha']) }}">
                            En la cola <span class="badge badge-light">{{ $conteos['sinFecha'] }}</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link py-1 @if($verDesc) active @endif"
                           href="{{ route('jugadores.verificarPersonas', $qs + ['tab' => 'sin-fecha', 'ver' => 'desconocidas']) }}">
                            Sin fecha conocida <span class="badge badge-light">{{ $conteos['sinFechaDesc'] }}</span>
                        </a>
                    </li>
                </ul>

                @if($sinFecha->total() == 0)
                    <div class="alert alert-info">
                        @if($verDesc)
                            Todavía no marcaste ninguna persona como sin fecha conocida.
                        @else
                            No queda ninguna persona esperando fecha. Las que marcaste como sin fecha
                            conocida están en la otra solapa.
                        @endif
                    </div>
                @endif

                @if(!$verDesc && $sinFecha->total() > 0)
                <div class="card mb-3">
                    <div class="card-body py-2">
                        <form method="POST" action="{{ route('personas.fechas.completar') }}">
                            @csrf
                            <div class="form-inline">
                                <label class="mr-2 mb-0">Consultar la API de a</label>
                                <select name="limite" class="form-control form-control-sm mr-3">
                                    <option value="50">50</option>
                                    <option value="200">200</option>
                                    <option value="500" selected>500</option>
                                    <option value="1000">1000</option>
                                </select>

                                <label class="mr-2 mb-0">
                                    <input type="checkbox" name="html" value="1"> también la ficha web, de a
                                </label>
                                <select name="limite_html" class="form-control form-control-sm mr-3">
                                    <option value="10">10</option>
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>

                                <label class="mr-3 mb-0">
                                    <input type="checkbox" name="reintentar" value="1"> reintentar las ya consultadas
                                </label>

                                <button class="btn btn-sm btn-primary">Completar desde Transfermarkt</button>
                            </div>
                        </form>
                        <small class="text-muted d-block mt-2">
                            <strong>La API</strong> es gratis y va de a 50 por llamada, pero para los árbitros casi
                            nunca trae la fecha. <strong>La ficha web</strong> sí la muestra, pero Transfermarkt nos
                            bloquea el HTML directo y sale <strong>1 crédito de ScraperAPI por ficha</strong>, así que
                            va con su propio tope. Lo que se consulta y no da fecha queda anotado y no se vuelve a
                            pedir, salvo que tildes "reintentar".
                        </small>
                    </div>
                </div>
                @endif

                {{-- El <form> va vacío y afuera de la tabla, y los tildes se le
                     enganchan con el atributo form=: un <form> no puede envolver
                     filas de una tabla sin que el navegador lo saque de ahí.
                     Mismo truco que usa la pestaña "Sin registros". --}}
                @if($sinFecha->total() > 0)
                <form id="formFechas" method="POST" action="{{ route('personas.fechas.desconocidas') }}"
                      onsubmit="return confirmarFechas(event)"></form>
                <input type="hidden" name="_token" value="{{ csrf_token() }}" form="formFechas">

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="small mb-0"><input type="checkbox" id="tildarTodos"> tildar todo lo visible</label>
                    <div class="form-inline">
                        @if($verDesc)
                            <button form="formFechas" name="accion" value="reabrir" class="btn btn-sm btn-outline-primary">
                                Volver a la cola
                            </button>
                        @else
                            {{-- Enter acá enviaría el form sin botón, y sin botón no hay
                                 acción: el controller recibiría "accion" vacío. --}}
                            <input type="text" name="motivo" form="formFechas" maxlength="200"
                                   class="form-control form-control-sm mr-2" style="width:280px;"
                                   onkeydown="if (event.key === 'Enter') { event.preventDefault(); }"
                                   placeholder="dónde buscaste (opcional): TM, BDFA, Wikipedia...">
                            <button form="formFechas" name="accion" value="marcar" class="btn btn-sm btn-secondary">
                                Marcar como sin fecha conocida
                            </button>
                        @endif
                    </div>
                </div>

                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th style="width:28px;"></th>
                            <th></th><th>Id</th><th>Mostrar</th><th>Apellido</th><th>Nombre</th>
                            <th>Nacionalidad</th><th>Roles</th><th>Transfermarkt</th>
                            @if($verDesc)<th>Marcada</th>@endif
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($sinFecha as $p)
                        @php $tm = $tmDe[$p->id] ?? null; @endphp
                        <tr>
                            <td>
                                <input type="checkbox" name="personas[]" value="{{ $p->id }}"
                                       form="formFechas" class="tildable">
                            </td>
                            <td>
                                @if($p->foto)
                                    <img class="imgCircle" src="{{ url('images/'.$p->foto) }}" alt="">
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
                            <td>
                                @if($p->jugador)<span class="badge badge-primary">Jugador {{ $p->jugador->id }}</span>@endif
                                @if($p->tecnico)<span class="badge badge-success">DT {{ $p->tecnico->id }}</span>@endif
                                @if($p->arbitro)<span class="badge badge-warning">Árbitro {{ $p->arbitro->id }}</span>@endif
                            </td>
                            <td>
                                @if($tm && !empty($tm['tm']))
                                    @php
                                        $tramo = $tm['tipo'] === 'tecnico' ? 'trainer'
                                               : ($tm['tipo'] === 'arbitro' ? 'schiedsrichter' : 'spieler');
                                    @endphp
                                    <a href="https://www.transfermarkt.es/-/profil/{{ $tramo }}/{{ $tm['tm'] }}"
                                       target="_blank" class="small">TM {{ $tm['tm'] }}</a>
                                @else
                                    <span class="badge badge-secondary">sin TM</span>
                                @endif
                            </td>
                            @if($verDesc)
                                <td class="small text-muted">{{ $tm['motivo'] ?? '' }}</td>
                            @endif
                            <td>
                                @if($p->jugador)
                                    <a href="{{ route('jugadores.edit', $p->jugador->id) }}" target="_blank" class="small">editar</a>
                                @elseif($p->tecnico)
                                    <a href="{{ route('tecnicos.edit', $p->tecnico->id) }}" target="_blank" class="small">editar</a>
                                @elseif($p->arbitro)
                                    <a href="{{ route('arbitros.edit', $p->arbitro->id) }}" target="_blank" class="small">editar</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <div class="row">
                    <div class="col-md-9">{{ $sinFecha->links() }}</div>
                    <div class="col-md-3 text-right"><strong>Total: {{ $sinFecha->total() }}</strong></div>
                </div>
                @endif
            @endif
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var todos = document.getElementById('tildarTodos');
            if (todos) {
                todos.addEventListener('change', function () {
                    document.querySelectorAll('.tildable').forEach(function (c) { c.checked = todos.checked; });
                });
            }
        });

        function confirmarBorrado() {
            var n = document.querySelectorAll('.tildable:checked').length;
            if (n === 0) {
                alert('No tildaste ninguna persona.');
                return false;
            }
            return confirm('Se van a BORRAR ' + n + ' personas sin registros. No se puede deshacer. ¿Confirmás?');
        }

        function confirmarFechas(evento) {
            var n = document.querySelectorAll('.tildable:checked').length;
            if (n === 0) {
                alert('No tildaste ninguna persona.');
                return false;
            }

            // La acción sale del botón que disparó el envío, no de
            // document.activeElement: en Safari/iOS un <button> no queda
            // enfocado al clickearlo.
            var accion = (evento && evento.submitter && evento.submitter.value) ? evento.submitter.value : '';

            if (accion === 'reabrir') {
                return confirm('Devolver ' + n + ' personas a la cola de fechas?');
            }
            if (accion === 'marcar') {
                return confirm('Marcar ' + n + ' personas como sin fecha conocida? Salen de la cola y del '
                    + 'contador (no se borra nada y se puede deshacer).');
            }

            alert('No se pudo determinar la acción. Probá de nuevo apretando directamente el botón.');
            return false;
        }

        function confirmarLote(form, evento) {
            var n = document.querySelectorAll('.tildable:checked').length;
            if (n === 0) {
                alert('No tildaste ningún par.');
                return false;
            }

            // La acción se toma del botón que realmente disparó el envío.
            // No se usa document.activeElement: en Safari/iOS un <button> no
            // queda enfocado al clickearlo y el cartel diría una cosa mientras
            // se ejecuta la otra (y una de las dos borra personas).
            var accion = '';
            if (evento && evento.submitter && evento.submitter.value) {
                accion = evento.submitter.value;
            } else if (window.__accionLote) {
                accion = window.__accionLote;
            }

            if (accion === 'fusionar') {
                return confirm('Se van a FUSIONAR ' + n + ' pares, conservando en cada uno la persona sugerida (★) y BORRANDO la otra. ¿Confirmás?');
            }
            if (accion === 'descartar') {
                return confirm('Marcar ' + n + ' pares como personas distintas?');
            }

            alert('No se pudo determinar la acción. Probá de nuevo apretando directamente uno de los dos botones.');
            return false;
        }
    </script>
@endsection
