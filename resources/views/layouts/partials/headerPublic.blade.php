@php
    $torneoActivo = Session::get('codigoTorneo');

    // Zona (país / región) y otras temporadas del torneo que se está mirando.
    $ctxTorneo = $torneoActivo ? \App\Services\MenuTorneos::contexto($torneoActivo) : null;
    $menuVersion = \App\Services\MenuTorneos::zonas()['version'];
@endphp

<header>
    {{-- Navegación principal --}}
    <nav class="navbar navbar-expand-lg t-navbar sticky-top">
        <div class="container">

            <a class="navbar-brand" href="{{ route('fechas.fixture') }}">
                <img src="{{ asset('images/icon_ball.png') }}" alt="" height="24">
                <span class="d-none d-xxl-inline">{{ __('Resultados y estadísticas') }}</span>
                <span class="d-xxl-none">{{ __('Torneos') }}</span>
            </a>

            {{-- Controles siempre a la vista --}}
            <div class="d-flex align-items-center gap-2 order-lg-4">
                {{-- Idioma: la misma página en el otro idioma (/en/… ↔ /…) --}}
                @foreach(idiomas_sitio() as $codIdioma => $nomIdioma)
                    @if($codIdioma !== app()->getLocale())
                        <a class="t-boton-icono t-idioma" href="{{ url_idioma($codIdioma) }}"
                           hreflang="{{ $codIdioma }}" lang="{{ $codIdioma }}" title="{{ $nomIdioma }}">{{ strtoupper($codIdioma) }}</a>
                    @endif
                @endforeach

                <button class="t-boton-icono" type="button" id="boton-densidad"
                        aria-label="{{ __('Cambiar la densidad de las tablas') }}" title="{{ __('Densidad de las tablas') }}">
                    <i class="bi bi-arrows-collapse" id="icono-densidad"></i>
                </button>

                <button class="t-boton-icono" type="button" id="boton-tema" aria-label="{{ __('Cambiar tema') }}">
                    <i class="bi bi-moon-stars" id="icono-tema"></i>
                </button>

                <button class="navbar-toggler border-0 p-1" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#mainNavbar"
                        aria-controls="mainNavbar" aria-label="{{ __('Abrir menú') }}">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>

            {{-- Buscador general (escritorio) --}}
            <form class="t-buscador d-none d-lg-flex order-lg-3" action="{{ route('buscar') }}" method="GET" role="search">
                <i class="bi bi-search"></i>
                <input type="search" name="q" id="buscador-general" class="form-control form-control-sm"
                       placeholder="{{ __('Buscar equipo, jugador…') }}" autocomplete="off"
                       value="{{ request()->routeIs('buscar') ? request('q') : '' }}">
            </form>

            {{-- Menú: panel lateral en celular, barra normal en escritorio --}}
            <div class="offcanvas offcanvas-end t-offcanvas order-lg-2" tabindex="-1" id="mainNavbar" aria-labelledby="mainNavbarTitulo">
                <div class="offcanvas-header">
                    <span class="offcanvas-title t-grupo-nombre" id="mainNavbarTitulo">{{ __('Menú') }}</span>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('Cerrar') }}"></button>
                </div>

                <div class="offcanvas-body">

                    {{-- Buscador general (celular) --}}
                    <form class="t-buscador d-lg-none mb-3" action="{{ route('buscar') }}" method="GET" role="search">
                        <i class="bi bi-search"></i>
                        <input type="search" name="q" class="form-control form-control-sm"
                               placeholder="{{ __('Buscar equipo, jugador…') }}" autocomplete="off">
                    </form>

                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">

                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('fechas.fixture') ? 'active' : '' }}"
                               href="{{ route('fechas.fixture') }}">{{ __('Partidos') }}</a>
                        </li>

                        {{--
                            Torneos: panel por país / región -> competencia -> temporada.
                            El contenido lo arma torneos.js con el JSON de torneos.menuJson
                            la primera vez que se abre. Sin JS, el enlace lleva a /competiciones.
                        --}}
                        <li class="nav-item dropdown t-nav-mega">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('torneos.explorar') ? 'active' : '' }}"
                               href="{{ route('torneos.explorar') }}" id="torneosDropdown" role="button"
                               data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">{{ __('Torneos') }}</a>

                            <div class="dropdown-menu t-mega" id="menu-torneos" aria-labelledby="torneosDropdown"
                                 data-url="{{ route('torneos.menuJson', ['v' => $menuVersion]) }}"
                                 data-explorar="{{ route('torneos.explorar') }}"
                                 data-zona="{{ $ctxTorneo ? $ctxTorneo['zona']['clave'] : '' }}"
                                 data-activo="{{ $torneoActivo }}">

                                <div class="t-mega-cabeza">
                                    <label class="t-mega-buscador">
                                        <i class="bi bi-search"></i>
                                        <input type="search" id="mega-buscar" autocomplete="off"
                                               placeholder="{{ __('Buscar torneo, país o año…') }}" aria-label="{{ __('Buscar torneo, país o año') }}">
                                    </label>
                                    <div class="t-mega-recientes" id="mega-recientes" hidden>
                                        <span class="t-mega-rot">{{ __('Vistos hace poco') }}</span>
                                        <div class="t-mega-chips" id="recientesMenu"></div>
                                    </div>
                                </div>

                                <div class="t-mega-cuerpo">
                                    <nav class="t-mega-zonas" id="mega-zonas" aria-label="{{ __('Países y regiones') }}"></nav>
                                    <div class="t-mega-lista" id="mega-lista" aria-live="polite">
                                        <div class="t-mega-aviso">{{ __('Cargando torneos…') }}</div>
                                    </div>
                                </div>

                                <div class="t-mega-pie">
                                    <a href="{{ route('torneos.explorar') }}">{{ __('Ver todas las competiciones') }} <i class="bi bi-arrow-right"></i></a>
                                </div>
                            </div>
                        </li>

                        {{-- Protagonistas --}}
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="protagonistasDropdown" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false">{{ __('Protagonistas') }}</a>
                            <ul class="dropdown-menu t-menu-corto" aria-labelledby="protagonistasDropdown">
                                <li><a class="dropdown-item" href="{{route('torneos.arqueros')}}">{{ __('Arqueros') }}</a></li>
                                <li><a class="dropdown-item" href="{{route('torneos.goleadores')}}">{{ __('Goleadores') }}</a></li>
                                <li><a class="dropdown-item" href="{{route('torneos.jugadores')}}">{{ __('Jugadores') }}</a></li>
                                <li><a class="dropdown-item" href="{{route('torneos.tarjetas')}}">{{ __('Tarjetas') }}</a></li>
                                <li><a class="dropdown-item" href="{{route('torneos.tecnicos')}}">{{ __('Técnicos') }}</a></li>
                            </ul>
                        </li>

                        {{-- Equipos --}}
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="equiposDropdown" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false">{{ __('Equipos') }}</a>
                            <ul class="dropdown-menu t-menu-corto" aria-labelledby="equiposDropdown">
                                <li><a class="dropdown-item" href="{{route('torneos.historiales')}}">{{ __('Historiales') }}</a></li>
                                <li><a class="dropdown-item" href="{{route('torneos.posiciones')}}">{{ __('Tabla Histórica') }}</a></li>
                                <li><a class="dropdown-item" href="{{route('torneos.titulos')}}">{{ __('Títulos') }}</a></li>
                            </ul>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('torneos.estadisticasOtras') ? 'active' : '' }}"
                               href="{{ route('torneos.estadisticasOtras') }}">{{ __('Estadísticas') }}</a>
                        </li>

                    </ul>
                </div>
            </div>
        </div>
    </nav>

    {{-- Barra del torneo elegido --}}
    @if(Session::has('codigoTorneo'))
        @php $tId = Session::get('codigoTorneo'); @endphp
        <div class="t-barra-torneo"
             data-torneo-id="{{ $tId }}"
             data-torneo-nombre="{{ Session::get('nombreTorneo') }}"
             data-torneo-escudo="{{ Session::has('escudoTorneo') ? url('images/'.Session::get('escudoTorneo')) : '' }}"
             data-torneo-url="{{ route('fechas.ver', ['torneoId' => $tId]) }}">
            <div class="container t-barra-inner">
                <div class="t-barra-titulo">
                    @if($ctxTorneo)
                        <a class="t-barra-zona" href="{{ route('torneos.explorar', ['zona' => $ctxTorneo['zona']['clave']]) }}"
                           title="{{ __('Más torneos de :zona', ['zona' => $ctxTorneo['zona']['nombre']]) }}">
                            @if(!empty($ctxTorneo['zona']['escudo']))
                            <img class="escudo-conf" src="{{ url('images/'.$ctxTorneo['zona']['escudo']) }}" alt="" onerror="this.remove()">
                        @elseif($ctxTorneo['zona']['bandera'])
                                <img class="bandera" src="{{ url('images/'.$ctxTorneo['zona']['bandera']) }}" alt="" onerror="this.remove()">
                            @else
                                <i class="bi bi-globe2"></i>
                            @endif
                            <span>{{ $ctxTorneo['zona']['nombre'] }}</span>
                        </a>
                        <i class="bi bi-chevron-right t-barra-sep" aria-hidden="true"></i>
                    @endif

                    <x-escudo :src="Session::get('escudoTorneo')" :nombre="Session::get('nombreTorneo')"/>

                    @php
                        $edicionesBarra = ($ctxTorneo && $ctxTorneo['competencia']) ? $ctxTorneo['competencia']['ediciones'] : [];
                    @endphp

                    @if(count($edicionesBarra) > 1)
                        {{-- Nombre + selector de temporada: se cambia de año sin volver al menú --}}
                        <span>{{ $ctxTorneo['torneo']->nombre }}</span>
                        <div class="dropdown">
                            <button class="t-barra-temporada dropdown-toggle" type="button" data-bs-toggle="dropdown"
                                    aria-expanded="false" aria-label="{{ __('Cambiar de temporada') }}">{{ $ctxTorneo['torneo']->year }}</button>
                            <ul class="dropdown-menu t-menu-corto t-menu-temporadas">
                                @foreach($edicionesBarra as $ed)
                                    <li>
                                        <a class="dropdown-item {{ $ed['id'] == $tId ? 'activo' : '' }}"
                                           href="{{ route('fechas.ver', ['torneoId' => $ed['id']]) }}">{{ $ed['year'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @else
                        <span>{{ Session::get('nombreTorneo') }}</span>
                    @endif
                </div>

                <ul class="nav">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('fechas.ver') ? 'active' : '' }}"
                           href="{{ route('fechas.ver', ['torneoId' => $tId]) }}">{{ __('Fixture') }}</a>
                    </li>

                    @if(Session::has('sessionPosiciones'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('grupos.posicionesPublic') ? 'active' : '' }}"
                               href="{{ route('grupos.posicionesPublic', ['torneoId' => $tId]) }}">{{ __('Posiciones') }}</a>
                        </li>
                    @endif

                    @if(Session::has('sessionPromedios'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('torneos.promediosPublic') ? 'active' : '' }}"
                               href="{{ route('torneos.promediosPublic', ['torneoId' => $tId]) }}">{{ __('Promedios') }}</a>
                        </li>
                    @endif

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('grupos.goleadoresPublic') ? 'active' : '' }}"
                           href="{{ route('grupos.goleadoresPublic', ['torneoId' => $tId]) }}">{{ __('Goleadores') }}</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('torneos.plantillas') ? 'active' : '' }}"
                           href="{{ route('torneos.plantillas', ['torneoId' => $tId]) }}">{{ __('Plantillas') }}</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('torneos.estadisticasTorneo') ? 'active' : '' }}"
                           href="{{ route('torneos.estadisticasTorneo', ['torneoId' => $tId]) }}">{{ __('Estadísticas') }}</a>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button">{{ __('Más') }}</a>
                        <ul class="dropdown-menu t-menu-corto">
                            @if(Session::has('sessionAcumulado'))
                                <li><a class="dropdown-item" href="{{ route('torneos.acumulado', ['torneoId' => $tId]) }}">{{ __('Acumulado') }}</a></li>
                            @endif
                            @if(Session::has('sessionPaenza'))
                                <li><a class="dropdown-item" href="{{ route('grupos.metodo', ['torneoId' => $tId]) }}">{{ __('Método Paenza') }}</a></li>
                            @endif
                            <li><a class="dropdown-item" href="{{ route('grupos.arqueros', ['torneoId' => $tId]) }}">{{ __('Arqueros') }}</a></li>
                            <li><a class="dropdown-item" href="{{ route('grupos.jugadores', ['torneoId' => $tId]) }}">{{ __('Jugadores') }}</a></li>
                            <li><a class="dropdown-item" href="{{ route('grupos.tarjetasPublic', ['torneoId' => $tId]) }}">{{ __('Tarjetas') }}</a></li>
                            <li><a class="dropdown-item" href="{{ route('grupos.tecnicos', ['torneoId' => $tId]) }}">{{ __('Técnicos') }}</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    @endif
</header>
