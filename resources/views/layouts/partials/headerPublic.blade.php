@php
    $menu = $torneosMenu ?? $torneos;

    $ligas = $menu->filter(function ($t) {
        return $t->tipo == 'Liga' && $t->ambito == 'Nacional';
    })->groupBy('year');

    $copas = $menu->filter(function ($t) {
        return $t->tipo == 'Copa' && $t->ambito == 'Nacional';
    })->groupBy('year');

    $internacionales = $menu->filter(function ($t) {
        return $t->ambito == 'Internacional';
    })->groupBy('year');

    $torneoActivo = Session::get('codigoTorneo');
@endphp

<header>
    {{-- Navegación principal --}}
    <nav class="navbar navbar-expand-lg t-navbar sticky-top">
        <div class="container">

            <a class="navbar-brand" href="{{ route('fechas.fixture') }}">
                <img src="{{ asset('images/icon_ball.png') }}" alt="" height="24">
                <span class="d-none d-xxl-inline">Resultados y estadísticas</span>
                <span class="d-xxl-none">Torneos</span>
            </a>

            {{-- Controles siempre a la vista --}}
            <div class="d-flex align-items-center gap-2 order-lg-4">
                <button class="t-boton-icono" type="button" id="boton-densidad"
                        aria-label="Cambiar la densidad de las tablas" title="Densidad de las tablas">
                    <i class="bi bi-arrows-collapse" id="icono-densidad"></i>
                </button>

                <button class="t-boton-icono" type="button" id="boton-tema" aria-label="Cambiar tema">
                    <i class="bi bi-moon-stars" id="icono-tema"></i>
                </button>

                <button class="navbar-toggler border-0 p-1" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#mainNavbar"
                        aria-controls="mainNavbar" aria-label="Abrir menú">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>

            {{-- Buscador general (escritorio) --}}
            <form class="t-buscador d-none d-lg-flex order-lg-3" action="{{ route('buscar') }}" method="GET" role="search">
                <i class="bi bi-search"></i>
                <input type="search" name="q" id="buscador-general" class="form-control form-control-sm"
                       placeholder="Buscar equipo, jugador…" autocomplete="off"
                       value="{{ request()->routeIs('buscar') ? request('q') : '' }}">
            </form>

            {{-- Menú: panel lateral en celular, barra normal en escritorio --}}
            <div class="offcanvas offcanvas-end t-offcanvas order-lg-2" tabindex="-1" id="mainNavbar" aria-labelledby="mainNavbarTitulo">
                <div class="offcanvas-header">
                    <span class="offcanvas-title t-grupo-nombre" id="mainNavbarTitulo">Menú</span>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
                </div>

                <div class="offcanvas-body">

                    {{-- Buscador general (celular) --}}
                    <form class="t-buscador d-lg-none mb-3" action="{{ route('buscar') }}" method="GET" role="search">
                        <i class="bi bi-search"></i>
                        <input type="search" name="q" class="form-control form-control-sm"
                               placeholder="Buscar equipo, jugador…" autocomplete="off">
                    </form>

                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">

                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('fechas.fixture') ? 'active' : '' }}"
                               href="{{ route('fechas.fixture') }}">Partidos</a>
                        </li>

                        {{-- Últimos torneos vistos: lo completa torneos.js con lo guardado en el navegador --}}
                        <li class="nav-item dropdown" id="menu-recientes" hidden>
                            <a class="nav-link dropdown-toggle" href="#" id="recientesDropdown" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false">Recientes</a>
                            <ul class="dropdown-menu t-menu-corto" id="recientesMenu" aria-labelledby="recientesDropdown"></ul>
                        </li>

                        @foreach([
                            ['id' => 'liga',  'label' => 'Ligas',         'grupos' => $ligas,           'placeholder' => 'Buscar liga o año...'],
                            ['id' => 'copa',  'label' => 'Copas',         'grupos' => $copas,           'placeholder' => 'Buscar copa o año...'],
                            ['id' => 'inter', 'label' => 'Internacional', 'grupos' => $internacionales, 'placeholder' => 'Buscar torneo o año...'],
                        ] as $m)
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" id="{{ $m['id'] }}Dropdown" role="button"
                                   data-bs-toggle="dropdown" aria-expanded="false">{{ $m['label'] }}</a>
                                <ul class="dropdown-menu t-menu-torneos" aria-labelledby="{{ $m['id'] }}Dropdown">
                                    <li class="t-menu-buscador">
                                        <input type="text" class="form-control form-control-sm"
                                               placeholder="{{ $m['placeholder'] }}"
                                               onkeyup="filterDropdown(this, '{{ $m['id'] }}DropdownMenu')">
                                    </li>
                                    <div id="{{ $m['id'] }}DropdownMenu">
                                        @foreach($m['grupos'] as $anio => $lista)
                                            <div class="t-menu-grupo" data-anio="{{ $anio }}">
                                                <div class="t-menu-anio">{{ $anio }}</div>
                                                @foreach($lista as $t)
                                                    <li>
                                                        <a class="dropdown-item {{ $torneoActivo == $t->id ? 'activo' : '' }}"
                                                           href="{{ route('fechas.ver', ['torneoId' => $t->id]) }}">
                                                            <x-escudo :src="$t->escudo" :nombre="$t->nombre" tam="sm"/>
                                                            <span>{{ $t->nombre }}</span>
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </div>
                                        @endforeach
                                    </div>
                                    <li class="t-menu-vacio" hidden>Sin torneos que coincidan</li>
                                </ul>
                            </li>
                        @endforeach

                        {{-- Protagonistas --}}
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="protagonistasDropdown" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false">Protagonistas</a>
                            <ul class="dropdown-menu t-menu-corto" aria-labelledby="protagonistasDropdown">
                                <li><a class="dropdown-item" href="{{route('torneos.arqueros')}}">Arqueros</a></li>
                                <li><a class="dropdown-item" href="{{route('torneos.goleadores')}}">Goleadores</a></li>
                                <li><a class="dropdown-item" href="{{route('torneos.jugadores')}}">Jugadores</a></li>
                                <li><a class="dropdown-item" href="{{route('torneos.tarjetas')}}">Tarjetas</a></li>
                                <li><a class="dropdown-item" href="{{route('torneos.tecnicos')}}">Técnicos</a></li>
                            </ul>
                        </li>

                        {{-- Equipos --}}
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="equiposDropdown" role="button"
                               data-bs-toggle="dropdown" aria-expanded="false">Equipos</a>
                            <ul class="dropdown-menu t-menu-corto" aria-labelledby="equiposDropdown">
                                <li><a class="dropdown-item" href="{{route('torneos.historiales')}}">Historiales</a></li>
                                <li><a class="dropdown-item" href="{{route('torneos.posiciones')}}">Tabla Histórica</a></li>
                                <li><a class="dropdown-item" href="{{route('torneos.titulos')}}">Títulos</a></li>
                            </ul>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('torneos.estadisticasOtras') ? 'active' : '' }}"
                               href="{{ route('torneos.estadisticasOtras') }}">Estadísticas</a>
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
                <span class="t-barra-titulo">
                    <x-escudo :src="Session::get('escudoTorneo')" :nombre="Session::get('nombreTorneo')"/>
                    {{ Session::get('nombreTorneo') }}
                </span>

                <ul class="nav">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('fechas.ver') ? 'active' : '' }}"
                           href="{{ route('fechas.ver', ['torneoId' => $tId]) }}">Fixture</a>
                    </li>

                    @if(Session::has('sessionPosiciones'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('grupos.posicionesPublic') ? 'active' : '' }}"
                               href="{{ route('grupos.posicionesPublic', ['torneoId' => $tId]) }}">Posiciones</a>
                        </li>
                    @endif

                    @if(Session::has('sessionPromedios'))
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('torneos.promediosPublic') ? 'active' : '' }}"
                               href="{{ route('torneos.promediosPublic', ['torneoId' => $tId]) }}">Promedios</a>
                        </li>
                    @endif

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('grupos.goleadoresPublic') ? 'active' : '' }}"
                           href="{{ route('grupos.goleadoresPublic', ['torneoId' => $tId]) }}">Goleadores</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('torneos.plantillas') ? 'active' : '' }}"
                           href="{{ route('torneos.plantillas', ['torneoId' => $tId]) }}">Plantillas</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('torneos.estadisticasTorneo') ? 'active' : '' }}"
                           href="{{ route('torneos.estadisticasTorneo', ['torneoId' => $tId]) }}">Estadísticas</a>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button">Más</a>
                        <ul class="dropdown-menu t-menu-corto">
                            @if(Session::has('sessionAcumulado'))
                                <li><a class="dropdown-item" href="{{ route('torneos.acumulado', ['torneoId' => $tId]) }}">Acumulado</a></li>
                            @endif
                            @if(Session::has('sessionPaenza'))
                                <li><a class="dropdown-item" href="{{ route('grupos.metodo', ['torneoId' => $tId]) }}">Método Paenza</a></li>
                            @endif
                            <li><a class="dropdown-item" href="{{ route('grupos.arqueros', ['torneoId' => $tId]) }}">Arqueros</a></li>
                            <li><a class="dropdown-item" href="{{ route('grupos.jugadores', ['torneoId' => $tId]) }}">Jugadores</a></li>
                            <li><a class="dropdown-item" href="{{ route('grupos.tarjetasPublic', ['torneoId' => $tId]) }}">Tarjetas</a></li>
                            <li><a class="dropdown-item" href="{{ route('grupos.tecnicos', ['torneoId' => $tId]) }}">Técnicos</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    @endif
</header>
