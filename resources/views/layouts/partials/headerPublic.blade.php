{{-- Bootstrap CSS --}}
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<header>
    {{-- Navbar principal --}}
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            {{-- Logo --}}
            <a class="navbar-brand fw-bold">
                <img src="{{ asset('images/icon_ball.png') }}" alt="Logo" height="30" class="me-2">
                Resultados y estadísticas
            </a>

            {{-- Botón hamburguesa móvil --}}
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar"
                    aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            {{-- Menú principal --}}
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    {{-- Partidos --}}
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('fechas.fixture') }}">Partidos</a>
                    </li>

                    {{-- Dropdown Ligas --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="ligaDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            Ligas
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="ligaDropdown">
                            <li>
                                <input type="text" class="form-control form-control-sm m-2"
                                       placeholder="Buscar liga..." onkeyup="filterDropdown(this, 'ligaDropdownMenu')">
                            </li>
                            <div id="ligaDropdownMenu">
                                @foreach($torneos as $torneo)
                                    @if($torneo->tipo=='Liga' && $torneo->ambito=='Nacional')
                                        <li><a class="dropdown-item" href="{{route('fechas.ver', ['torneoId' => $torneo->id])}}">
                                                {{$torneo->nombre}} - {{$torneo->year}}
                                            </a>
                                        </li>
                                    @endif
                                @endforeach
                            </div>
                        </ul>
                    </li>

                    {{-- Dropdown Copas --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="copaDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            Copas
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="copaDropdown">
                            <li>
                                <input type="text" class="form-control form-control-sm m-2"
                                       placeholder="Buscar copa..." onkeyup="filterDropdown(this, 'copaDropdownMenu')">
                            </li>
                            <div id="copaDropdownMenu">
                                @foreach($torneos as $torneo)
                                    @if($torneo->tipo=='Copa' && $torneo->ambito=='Nacional')
                                        <li><a class="dropdown-item" href="{{route('fechas.ver', ['torneoId' => $torneo->id])}}">
                                                {{$torneo->nombre}} - {{$torneo->year}}
                                            </a>
                                        </li>
                                    @endif
                                @endforeach
                            </div>
                        </ul>
                    </li>

                    {{-- Dropdown Internacional --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="interDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            Internacional
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="interDropdown">
                            <li>
                                <input type="text" class="form-control form-control-sm m-2"
                                       placeholder="Buscar torneo..." onkeyup="filterDropdown(this, 'interDropdownMenu')">
                            </li>
                            <div id="interDropdownMenu">
                                @foreach($torneos as $torneo)
                                    @if($torneo->ambito=='Internacional')
                                        <li><a class="dropdown-item" href="{{route('fechas.ver', ['torneoId' => $torneo->id])}}">
                                                {{$torneo->nombre}} - {{$torneo->year}}
                                            </a>
                                        </li>
                                    @endif
                                @endforeach
                            </div>
                        </ul>
                    </li>

                    {{-- Dropdown Protagonistas --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="protagonistasDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            Protagonistas
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="protagonistasDropdown">
                            <li><a class="dropdown-item" href="{{route('torneos.arqueros')}}">Arqueros</a></li>
                            <li><a class="dropdown-item" href="{{route('torneos.goleadores')}}">Goleadores</a></li>
                            <li><a class="dropdown-item" href="{{route('torneos.jugadores')}}">Jugadores</a></li>
                            <li><a class="dropdown-item" href="{{route('torneos.tarjetas')}}">Tarjetas</a></li>
                            <li><a class="dropdown-item" href="{{route('torneos.tecnicos')}}">Técnicos</a></li>
                        </ul>
                    </li>

                    {{-- Dropdown Equipos --}}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="equiposDropdown" role="button"
                           data-bs-toggle="dropdown" aria-expanded="false">
                            Equipos
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="equiposDropdown">
                            <li><a class="dropdown-item" href="{{route('torneos.historiales')}}">Historiales</a></li>
                            <li><a class="dropdown-item" href="{{route('torneos.posiciones')}}">Tabla Histórica</a></li>
                            <li><a class="dropdown-item" href="{{route('torneos.titulos')}}">Títulos</a></li>
                        </ul>
                    </li>

                    {{-- Estadísticas --}}
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('torneos.estadisticasOtras') }}">Estadísticas</a>
                    </li>

                </ul>
            </div>
        </div>
    </nav>

    {{-- Menú contextual de torneo seleccionado --}}
    @if(Session::has('codigoTorneo'))
        <div class="bg-light border-bottom">
            <div class="container d-flex align-items-center gap-3 py-2">
                @if(Session::has('escudoTorneo'))
                    <img src="{{ url('images/'.Session::get('escudoTorneo')) }}" alt="escudo" height="25">
                @endif

                <span class="fw-bold text-primary">{{ Session::get('nombreTorneo') }}</span>

                <ul class="nav nav-pills ms-3">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('fechas.ver', Session::get('codigoTorneo')) }}">Fixture</a>
                    </li>
                    @if(Session::has('sessionPosiciones'))
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">Tablas</a>
                            <ul class="dropdown-menu">
                                @if(Session::has('sessionAcumulado'))

                                    <li><a class="dropdown-item" href="{{route('torneos.acumulado',  array('torneoId' => Session::get('codigoTorneo')))}}">Acumulado</a></li>
                                @endif
                                @if(Session::has('sessionPaenza'))

                                        <li><a class="dropdown-item" href="{{route('grupos.metodo',  array('torneoId' => Session::get('codigoTorneo')))}}">Método Paenza</a></li>
                                @endif
                                <li><a class="dropdown-item" href="{{route('grupos.posicionesPublic',  array('torneoId' => Session::get('codigoTorneo')))}}">Posiciones</a></li>
                                @if(Session::has('sessionPromedios'))
                                    <li><a class="dropdown-item" href="{{route('torneos.promediosPublic',  array('torneoId' => Session::get('codigoTorneo')))}}">Promedios</a></li>
                                @endif
                            </ul>
                        </li>
                    @endif
                    <li class="nav-item dropdown">

                        <a class="nav-link dropdown-toggle" data-bs-toggle="dropdown" href="#">{{ __('Protagonistas') }}</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="{{route('grupos.arqueros',  array('torneoId' => Session::get('codigoTorneo')))}}">Arqueros</a></li>
                            <li><a class="dropdown-item" href="{{route('grupos.goleadoresPublic',  array('torneoId' => Session::get('codigoTorneo')))}}">Goleadores</a></li>
                            <li><a class="dropdown-item" href="{{route('grupos.jugadores',  array('torneoId' => Session::get('codigoTorneo')))}}">Jugadores</a></li>
                            <li><a class="dropdown-item" href="{{route('grupos.tarjetasPublic',  array('torneoId' => Session::get('codigoTorneo')))}}">Tarjetas</a></li>

                            <li><a class="dropdown-item" href="{{route('grupos.tecnicos',  array('torneoId' => Session::get('codigoTorneo')))}}">Técnicos</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{route('torneos.estadisticasTorneo',  array('torneoId' => Session::get('codigoTorneo')))}}">Estadísticas</a>

                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{route('torneos.plantillas',  array('torneoId' => Session::get('codigoTorneo')))}}">Plantillas</a>
                    </li>
                </ul>
            </div>
        </div>
    @endif
</header>
