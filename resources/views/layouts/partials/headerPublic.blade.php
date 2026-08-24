<header>
    {{-- Navegación principal --}}
    <nav class="navbar navbar-expand-lg t-navbar sticky-top">
        <div class="container">

            <a class="navbar-brand" href="{{ route('fechas.fixture') }}">
                <img src="{{ asset('images/icon_ball.png') }}" alt="" height="24">
                Resultados y estadísticas
            </a>

            {{-- Controles que quedan siempre a la vista --}}
            <div class="d-flex align-items-center gap-2 order-lg-3">
                <div class="t-seg d-none d-lg-inline-flex" role="group" aria-label="Densidad de la información">
                    <button type="button" data-densidad-valor="comodo">Cómodo</button>
                    <button type="button" data-densidad-valor="compacto">Compacto</button>
                </div>

                <button class="t-boton-icono" type="button" id="boton-tema" aria-label="Cambiar tema">
                    <i class="bi bi-moon-stars" id="icono-tema"></i>
                </button>

                <button class="navbar-toggler border-0 p-1" type="button" data-bs-toggle="collapse"
                        data-bs-target="#mainNavbar" aria-controls="mainNavbar"
                        aria-expanded="false" aria-label="Abrir menú">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>

            <div class="collapse navbar-collapse order-lg-2" id="mainNavbar">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('fechas.fixture') ? 'active' : '' }}"
                           href="{{ route('fechas.fixture') }}">Partidos</a>
                    </li>

                    {{-- Ligas --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="ligaDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">Ligas</a>
                        <ul class="dropdown-menu" aria-labelledby="ligaDropdown">
                            <li class="t-menu-buscador">
                                <input type="text" class="form-control form-control-sm"
                                       placeholder="Buscar liga..." onkeyup="filterDropdown(this, 'ligaDropdownMenu')">
                            </li>
                            <div id="ligaDropdownMenu">
                                @foreach($torneosMenu ?? $torneos as $torneo)
                                    @if($torneo->tipo=='Liga' && $torneo->ambito=='Nacional')
                                        <li>
                                            <a class="dropdown-item {{ Session::get('codigoTorneo') == $torneo->id ? 'activo' : '' }}"
                                               href="{{route('fechas.ver', ['torneoId' => $torneo->id])}}">
                                                <x-escudo :src="$torneo->escudo" :nombre="$torneo->nombre" tam="sm"/>
                                                <span>{{$torneo->nombre}}</span>
                                                <span class="t-anio">{{$torneo->year}}</span>
                                            </a>
                                        </li>
                                    @endif
                                @endforeach
                            </div>
                        </ul>
                    </li>

                    {{-- Copas --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="copaDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">Copas</a>
                        <ul class="dropdown-menu" aria-labelledby="copaDropdown">
                            <li class="t-menu-buscador">
                                <input type="text" class="form-control form-control-sm"
                                       placeholder="Buscar copa..." onkeyup="filterDropdown(this, 'copaDropdownMenu')">
                            </li>
                            <div id="copaDropdownMenu">
                                @foreach($torneosMenu ?? $torneos as $torneo)
                                    @if($torneo->tipo=='Copa' && $torneo->ambito=='Nacional')
                                        <li>
                                            <a class="dropdown-item {{ Session::get('codigoTorneo') == $torneo->id ? 'activo' : '' }}"
                                               href="{{route('fechas.ver', ['torneoId' => $torneo->id])}}">
                                                <x-escudo :src="$torneo->escudo" :nombre="$torneo->nombre" tam="sm"/>
                                                <span>{{$torneo->nombre}}</span>
                                                <span class="t-anio">{{$torneo->year}}</span>
                                            </a>
                                        </li>
                                    @endif
                                @endforeach
                            </div>
                        </ul>
                    </li>

                    {{-- Internacional --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="interDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">Internacional</a>
                        <ul class="dropdown-menu" aria-labelledby="interDropdown">
                            <li class="t-menu-buscador">
                                <input type="text" class="form-control form-control-sm"
                                       placeholder="Buscar torneo..." onkeyup="filterDropdown(this, 'interDropdownMenu')">
                            </li>
                            <div id="interDropdownMenu">
                                @foreach($torneosMenu ?? $torneos as $torneo)
                                    @if($torneo->ambito=='Internacional')
                                        <li>
                                            <a class="dropdown-item {{ Session::get('codigoTorneo') == $torneo->id ? 'activo' : '' }}"
                                               href="{{route('fechas.ver', ['torneoId' => $torneo->id])}}">
                                                <x-escudo :src="$torneo->escudo" :nombre="$torneo->nombre" tam="sm"/>
                                                <span>{{$torneo->nombre}}</span>
                                                <span class="t-anio">{{$torneo->year}}</span>
                                            </a>
                                        </li>
                                    @endif
                                @endforeach
                            </div>
                        </ul>
                    </li>

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
    </nav>

    {{-- Barra del torneo elegido --}}
    @if(Session::has('codigoTorneo'))
        <div class="t-barra-torneo">
            <div class="container t-barra-inner">
                <span class="t-barra-titulo">
                    <x-escudo :src="Session::get('escudoTorneo')" :nombre="Session::get('nombreTorneo')"/>
                    {{ Session::get('nombreTorneo') }}
                </span>

                <ul class="nav">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('fechas.ver') ? 'active' : '' }}"
                           href="{{ route('fechas.ver', array('torneoId' => Session::get('codigoTorneo'))) }}">Fixture</a>
                    </li>

                    @if(Session::has('sessionPosiciones'))
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button">Tablas</a>
                            <ul class="dropdown-menu">
                                @if(Session::has('sessionAcumulado'))
                                    <li><a class="dropdown-item" href="{{route('torneos.acumulado', array('torneoId' => Session::get('codigoTorneo')))}}">Acumulado</a></li>
                                @endif
                                @if(Session::has('sessionPaenza'))
                                    <li><a class="dropdown-item" href="{{route('grupos.metodo', array('torneoId' => Session::get('codigoTorneo')))}}">Método Paenza</a></li>
                                @endif
                                <li><a class="dropdown-item" href="{{route('grupos.posicionesPublic', array('torneoId' => Session::get('codigoTorneo')))}}">Posiciones</a></li>
                                @if(Session::has('sessionPromedios'))
                                    <li><a class="dropdown-item" href="{{route('torneos.promediosPublic', array('torneoId' => Session::get('codigoTorneo')))}}">Promedios</a></li>
                                @endif
                            </ul>
                        </li>
                    @endif

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#" role="button">{{ __('Protagonistas') }}</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{route('grupos.arqueros', array('torneoId' => Session::get('codigoTorneo')))}}">Arqueros</a></li>
                            <li><a class="dropdown-item" href="{{route('grupos.goleadoresPublic', array('torneoId' => Session::get('codigoTorneo')))}}">Goleadores</a></li>
                            <li><a class="dropdown-item" href="{{route('grupos.jugadores', array('torneoId' => Session::get('codigoTorneo')))}}">Jugadores</a></li>
                            <li><a class="dropdown-item" href="{{route('grupos.tarjetasPublic', array('torneoId' => Session::get('codigoTorneo')))}}">Tarjetas</a></li>
                            <li><a class="dropdown-item" href="{{route('grupos.tecnicos', array('torneoId' => Session::get('codigoTorneo')))}}">Técnicos</a></li>
                        </ul>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="{{route('torneos.estadisticasTorneo', array('torneoId' => Session::get('codigoTorneo')))}}">Estadísticas</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{route('torneos.plantillas', array('torneoId' => Session::get('codigoTorneo')))}}">Plantillas</a>
                    </li>
                </ul>
            </div>
        </div>
    @endif
</header>
