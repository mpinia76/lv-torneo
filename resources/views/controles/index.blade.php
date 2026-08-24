@extends('layouts.app')

@section('pageTitle', 'Controles de carga')

@section('content')
    {{-- Va acá adentro a propósito: el layout carga Bootstrap 3 después de
         app.css, así que una hoja propia en el <head> perdería contra él. --}}
    <link href="{{ asset('css/controles.css') }}" rel="stylesheet">

    <div class="container-fluid ctrl">

        <h1 class="display-6">Controles de carga</h1>
        <p class="ctrl-intro">
            Cada control busca una inconsistencia distinta entre el resultado del partido y lo que hay cargado.
            Se ejecuta solo el que estás mirando; los totales del costado se calculan aparte y se guardan 15 minutos.
        </p>

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (\Session::has('error'))
            <div class="alert alert-danger">{!! \Session::get('error') !!}</div>
        @endif
        @if (\Session::has('success'))
            <div class="alert alert-success">{{ \Session::get('success') }}</div>
        @endif

        {{-- ---------------------------------------------------------------
             Filtros. Aplican a todos los controles por igual y viajan en la
             URL, así que el total cacheado es el de ESE recorte.
        ---------------------------------------------------------------- --}}
        <div class="ctrl-filtros">
            <div class="ctrl-acciones">
                <form method="POST" action="{{ route('controles.recalcular') }}" style="display:inline">
                    @csrf
                    @foreach($filtros as $k => $v)
                        @if($v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
                    @endforeach
                    <input type="hidden" name="check" value="{{ $clave }}">
                    <button class="btn btn-default btn-sm" title="Vuelve a calcular los totales del menú">
                        Recalcular totales
                    </button>
                </form>
                <a href="{{ route('torneos.index') }}" class="btn btn-success btn-sm">Volver a torneos</a>
            </div>

            <form method="GET" action="{{ route('controles.index') }}" style="display:inline">
                <input type="hidden" name="check" value="{{ $clave }}">

                <div class="ctrl-campo">
                    <label>Año</label>
                    <select name="year" onchange="this.form.submit()">
                        <option value="">Todos</option>
                        @foreach($anios as $anio)
                            <option value="{{ $anio }}" @if($filtros['year'] == $anio) selected @endif>{{ $anio }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="ctrl-campo">
                    <label>Torneo</label>
                    <select name="torneo">
                        <option value="">Todos</option>
                        @foreach($torneos as $id => $nombre)
                            <option value="{{ $id }}" @if($filtros['torneo'] == $id) selected @endif>{{ $nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="ctrl-campo">
                    <label>Equipo o torneo</label>
                    <input type="text" name="q" value="{{ $filtros['q'] }}" placeholder="buscar...">
                </div>

                <div class="ctrl-campo">
                    <button class="btn btn-primary btn-sm">Filtrar</button>
                    @if($filtros['year'] || $filtros['torneo'] || $filtros['q'])
                        <a class="btn btn-default btn-sm" href="{{ route('controles.index', ['check' => $clave]) }}">Limpiar</a>
                    @endif
                </div>
            </form>
        </div>

        <div class="row">

            {{-- --------------------------------------------------- menú --- --}}
            <div class="col-md-3">
                {{-- OJO con los nombres de las variables de este @foreach: al final
                     de la vista, @extends le pasa al layout TODO lo que quedó
                     definido acá (get_defined_vars()), y el footer hace
                     `@if(isset($grupo) && $grupo->penales)` esperando un modelo
                     Grupo. Una variable llamada $grupo rompe el footer entero. --}}
                <div class="ctrl-menu">
                    @foreach($grupos as $nombreGrupo => $chequeosGrupo)
                        <div class="ctrl-menu-grupo">{{ $nombreGrupo }}</div>
                        @foreach($chequeosGrupo as $c => $d)
                            <a href="{{ route('controles.index', array_filter(['check' => $c] + $filtros)) }}"
                               class="{{ $c === $clave ? 'activo' : '' }}"
                               title="{{ $d['ayuda'] }}">
                                @if($c === $clave)
                                    {{-- El activo ya tiene el total: es el de la tabla. --}}
                                    <span class="ctrl-total {{ $filas->total() ? 'hay' : 'limpio' }}">{{ $filas->total() }}</span>
                                @else
                                    <span class="ctrl-total cargando" data-check="{{ $c }}">·</span>
                                @endif
                                {{ $d['titulo'] }}
                            </a>
                        @endforeach
                    @endforeach
                </div>
            </div>

            {{-- ----------------------------------------- chequeo activo --- --}}
            <div class="col-md-9">

                <div class="ctrl-cabecera">
                    <h2>{{ $def['grupo'] }} · {{ $def['titulo'] }}</h2>
                    <p class="ctrl-ayuda">{{ $def['ayuda'] }}</p>
                </div>

                @if(!empty($resumenRoles))
                    <div class="ctrl-aviso">
                        Roles cargados en toda la base:
                        @foreach($resumenRoles as $rol => $total)
                            <span class="ctrl-chip neutro">{{ $rol }}: {{ number_format($total, 0, ',', '.') }}</span>
                        @endforeach
                    </div>
                @endif

                @if(!empty($def['roles']))
                    <div class="ctrl-filtro-rol">
                        Ver solo a los que les falta:
                        <a href="{{ route('controles.index', array_filter(['check' => $clave] + array_merge($filtros, ['rol' => null]))) }}"
                           class="{{ $filtros['rol'] ? '' : 'activo' }}">cualquiera de los tres</a>
                        @foreach($rolesTerna as $rol => $etiqueta)
                            <a href="{{ route('controles.index', array_filter(['check' => $clave] + array_merge($filtros, ['rol' => $rol]))) }}"
                               class="{{ $filtros['rol'] === $rol ? 'activo' : '' }}">{{ $etiqueta }}</a>
                        @endforeach
                    </div>
                @endif

                @if(!empty($def['aplicar']))
                    <div class="ctrl-aviso">
                        <form method="POST" action="{{ route('controles.penales.aplicar') }}"
                              onsubmit="return confirm('Se van a crear los penales convertidos de todos los goles de penal que entren en el filtro actual. ¿Seguimos?')">
                            @csrf
                            @foreach($filtros as $k => $v)
                                @if($v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
                            @endforeach
                            <button class="btn btn-warning btn-sm">Crear los penales faltantes</button>
                            <span style="margin-left:.5rem">
                                Crea la fila en <code>penals</code> con el arquero que estaba en cancha.
                                Los que dicen "no se pudo determinar" quedan afuera.
                            </span>
                        </form>
                    </div>
                @endif

                @include('controles._tabla')

            </div>
        </div>
    </div>
@endsection

@section('bottom')
    <script>
        // Los totales del menú se piden de a uno y en orden: son consultas
        // pesadas y no tiene sentido largarlas todas juntas contra la base.
        (function () {
            var pendientes = Array.prototype.slice.call(document.querySelectorAll('.ctrl-total[data-check]'));
            var filtros = @json(array_filter($filtros));

            function url(check) {
                var qs = ['check=' + encodeURIComponent(check)];
                for (var k in filtros) {
                    if (filtros.hasOwnProperty(k)) {
                        qs.push(encodeURIComponent(k) + '=' + encodeURIComponent(filtros[k]));
                    }
                }
                return '{{ route('controles.conteo') }}?' + qs.join('&');
            }

            function siguiente() {
                var el = pendientes.shift();
                if (!el) { return; }

                fetch(url(el.getAttribute('data-check')), {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                    .then(function (r) { return r.json(); })
                    .then(function (d) {
                        el.textContent = d.total;
                        el.className = 'ctrl-total ' + (d.total > 0 ? 'hay' : 'limpio');
                    })
                    .catch(function () { el.textContent = '?'; })
                    .then(siguiente);
            }

            siguiente();
        })();
    </script>
@endsection
