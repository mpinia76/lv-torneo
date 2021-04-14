<nav class="navbar navbar-default navbar-static-top">
    <div class="container">
        <div class="navbar-header">

            <!-- Collapsed Hamburger -->
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#app-navbar-collapse">
                <span class="sr-only">Toggle Navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>

            <!-- Branding Image -->

        </div>

        <div class="collapse navbar-collapse" id="app-navbar-collapse">
            <!-- Left Side Of Navbar -->
            <ul class="nav navbar-nav navbar-left">

                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{{ __('Torneos') }} <span class="caret"></span></a>

                    <ul class="dropdown-menu" role="menu">
                        @foreach($torneos as $torneo)
                        <li><a class="dropdown-item" href="{{route('fechas.ver',  array('torneoId' => $torneo->id))}}">
                                {{$torneo->nombre}} - {{$torneo->year}}
                            </a>
                        </li>

                        @endforeach


                    </ul>

                </li>
            </ul>

            <ul class="nav navbar-nav navbar-left">

                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{{ __('Opciones') }} <span class="caret"></span></a>

                    <ul class="dropdown-menu" role="menu">

                        <li><a class="dropdown-item" href="{{route('fechas.ver',  array('torneoId' => Session::get('codigoTorneo')))}}">
                                Fechas
                            </a>
                        </li>

                        <li><a class="dropdown-item" href="{{route('grupos.posicionesPublic',  array('torneoId' => Session::get('codigoTorneo')))}}">
                                Posiciones
                            </a>
                        </li>

                        <li><a class="dropdown-item" href="{{route('grupos.goleadoresPublic',  array('torneoId' => Session::get('codigoTorneo')))}}">
                                Goleadores
                            </a>
                        </li>

                        <li><a class="dropdown-item" href="{{route('grupos.tarjetasPublic',  array('torneoId' => Session::get('codigoTorneo')))}}">
                                Tarjetas
                            </a>
                        </li>

                        <li><a class="dropdown-item" href="{{route('torneos.promediosPublic',  array('torneoId' => Session::get('codigoTorneo')))}}">
                                Promedios
                            </a>
                        </li>








                    </ul>

                </li>
            </ul>

            <!-- Right Side Of Navbar -->
            <ul class="nav navbar-nav navbar-right">

                    <li><a href="#">{{Session::get('nombreTorneo')}}</a></li>

            </ul>


        </div>
    </div>
</nav>
