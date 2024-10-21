<style>
    /* Estilos para el menú horizontal */
    #horizontalMenu {
        display: none;
        background-color: #ccf1cd;
        border-bottom: 1px solid #ddd;
        padding: 10px 0;
    }
    #horizontalMenu ul {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        justify-content: left;
    }
    #horizontalMenu ul li {
        padding: 0 15px;
    }
    #horizontalMenu ul li a {
        color: #333;
        text-decoration: none;
        padding: 5px 10px;
        display: block;
    }
    #horizontalMenu ul li a:hover {
        background-color: #ddd;
    }
    .dropdown-menu {
        display: none;
    }
    .dropdown-menu.show {
        display: block;
    }
    /* Estilos para móviles */
    @media (max-width: 768px) {
        /* Convertir menú horizontal a vertical */
        #horizontalMenu ul {
            display: block; /* Hacer que los elementos se apilen verticalmente */
        }

        #horizontalMenu ul li {
            padding: 10px 0; /* Espaciado adecuado entre elementos */
        }

        #horizontalMenu ul li a {
            padding: 10px 15px; /* Aumentar el área clicable */
            display: block;
            width: 100%; /* Ocupa todo el ancho del contenedor */
        }
    }

</style>
<nav class="navbar navbar-default navbar-static-top">
    <div class="container">


        <div class="collapse navbar-collapse" id="app-navbar-collapse">
            <!-- Left Side Of Navbar -->
            <ul class="nav navbar-nav navbar-left">
                <li><a class="dropdown-item" href="{{route('fechas.fixture')}}">Partidos</a></li>

            </ul>
            <ul class="nav navbar-nav navbar-left">
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{{ __('Ligas nacionales') }} <span class="caret"></span></a>
                    <ul id="dropdownLiga" class="dropdown-menu" role="menu">
                        <input type="text" placeholder="Buscar.." class="searchFieldLiga">
                        @foreach($torneos as $torneo)
                            @if($torneo->tipo=='Liga' && $torneo->ambito=='Nacional')
                            <li><a class="dropdown-item" href="{{route('fechas.ver',  array('torneoId' => $torneo->id))}}">
                                    {{$torneo->nombre}} - {{$torneo->year}}
                                </a>
                            </li>
                            @endif
                        @endforeach
                    </ul>
                </li>
            </ul>
            <ul class="nav navbar-nav navbar-left">
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{{ __('Copas nacionales') }} <span class="caret"></span></a>
                    <ul id="dropdownCopa" class="dropdown-menu" role="menu">
                        <input type="text" placeholder="Buscar.." class="searchFieldCopa">
                        @foreach($torneos as $torneo)
                            @if($torneo->tipo=='Copa' && $torneo->ambito=='Nacional')
                            <li><a class="dropdown-item" href="{{route('fechas.ver',  array('torneoId' => $torneo->id))}}">
                                    {{$torneo->nombre}} - {{$torneo->year}}
                                </a>
                            </li>
                            @endif
                        @endforeach
                    </ul>
                </li>
            </ul>
            <ul class="nav navbar-nav navbar-left">
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{{ __('Iternacionales') }} <span class="caret"></span></a>
                    <ul id="dropdownInternacional" class="dropdown-menu" role="menu">
                        <input type="text" placeholder="Buscar.." class="searchFieldInternacional">
                        @foreach($torneos as $torneo)
                            @if($torneo->ambito=='Internacional')
                                <li><a class="dropdown-item" href="{{route('fechas.ver',  array('torneoId' => $torneo->id))}}">
                                        {{$torneo->nombre}} - {{$torneo->year}}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </li>
            </ul>
            <ul class="nav navbar-nav navbar-left">
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{{ __('Protagonistas') }} <span class="caret"></span></a>
                    <ul class="dropdown-menu" role="menu">
                        <li><a class="dropdown-item" href="{{route('torneos.arqueros')}}">Arqueros</a></li>
                        <li><a class="dropdown-item" href="{{route('torneos.goleadores')}}">Goleadores</a></li>


                        <li><a class="dropdown-item" href="{{route('torneos.jugadores')}}">Jugadores</a></li>

                        <li><a class="dropdown-item" href="{{route('torneos.tarjetas')}}">Tarjetas</a></li>
                        <li><a class="dropdown-item" href="{{route('torneos.tecnicos')}}">Técnicos</a></li>

                    </ul>
                </li>
            </ul>
            <ul class="nav navbar-nav navbar-left">
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{{ __('Equipos') }} <span class="caret"></span></a>
                    <ul class="dropdown-menu" role="menu">

                        <li><a class="dropdown-item" href="{{route('torneos.historiales')}}">Historiales</a></li>

                        <li><a class="dropdown-item" href="{{route('torneos.posiciones')}}">Tabla Histórica</a></li>

                        <li><a class="dropdown-item" href="{{route('torneos.titulos')}}">Títulos</a></li>
                    </ul>
                </li>
            </ul>
            <ul class="nav navbar-nav navbar-left">
                <li><a class="dropdown-item" href="{{route('torneos.estadisticasOtras')}}">Estadísticas</a></li>

            </ul>

            <!-- Right Side Of Navbar -->
            <ul class="nav navbar-nav navbar-right">

            </ul>
        </div>
    </div>

    <!-- Menú horizontal -->
    @if(Session::has('codigoTorneo'))
        <div id="horizontalMenu" class="navbar-collapse collapse">
            <ul>
                <li><a href="#" style="color: #4ea3e7;font-weight: bold;">{{Session::get('nombreTorneo')}}</a></li>
                <li><a class="dropdown-item" href="{{route('fechas.ver',  array('torneoId' => Session::get('codigoTorneo')))}}">Fixture</a></li>
                @if(Session::has('sessionPosiciones'))
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{{ __('Tablas') }} <span class="caret"></span></a>
                    <ul class="dropdown-menu" role="menu" style="display: none">
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
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{{ __('Protagonistas') }} <span class="caret"></span></a>
                    <ul class="dropdown-menu" role="menu" style="display: none">
                        <li><a class="dropdown-item" href="{{route('grupos.arqueros',  array('torneoId' => Session::get('codigoTorneo')))}}">Arqueros</a></li>
                        <li><a class="dropdown-item" href="{{route('grupos.goleadoresPublic',  array('torneoId' => Session::get('codigoTorneo')))}}">Goleadores</a></li>
                        <li><a class="dropdown-item" href="{{route('grupos.jugadores',  array('torneoId' => Session::get('codigoTorneo')))}}">Jugadores</a></li>
                        <li><a class="dropdown-item" href="{{route('grupos.tarjetasPublic',  array('torneoId' => Session::get('codigoTorneo')))}}">Tarjetas</a></li>

                        <li><a class="dropdown-item" href="{{route('grupos.tecnicos',  array('torneoId' => Session::get('codigoTorneo')))}}">Técnicos</a></li>
                    </ul>
                </li>
                <li><a class="dropdown-item" href="{{route('torneos.estadisticasTorneo',  array('torneoId' => Session::get('codigoTorneo')))}}">Estadísticas</a></li>

                <li><a class="dropdown-item" href="{{route('torneos.plantillas',  array('torneoId' => Session::get('codigoTorneo')))}}">Plantillas</a></li>


            </ul>
        </div>
    @endif

    <script>
        function showHorizontalMenu(torneoId) {
            // Mostrar el menú horizontal
            document.getElementById('horizontalMenu').style.display = 'block';

            // Actualizar los enlaces del menú horizontal con el torneo seleccionado
            var menuLinks = document.querySelectorAll('#horizontalMenu a');
            menuLinks.forEach(function(link) {
                var href = link.getAttribute('href');
                var newHref = href.replace(/\d+/, torneoId);
                link.setAttribute('href', newHref);
            });
        }

        document.querySelector('.searchFieldLiga').addEventListener('keyup', filterDropdownLiga);
        function filterDropdownLiga() {
            var inputSearch, filterText, ul, li, links, i, div;
            inputSearch = document.querySelector(".searchFieldLiga");
            filterText = inputSearch.value.toUpperCase();
            div = document.getElementById("dropdownLiga");
            links = div.getElementsByTagName("a");
            for (i = 0; i < links.length; i++) {
                txtValue = links[i].textContent || links[i].innerText;
                if (txtValue.toUpperCase().indexOf(filterText) > -1) {
                    links[i].style.display = "";
                } else {
                    links[i].style.display = "none";
                }
            }
        }

        document.querySelector('.searchFieldCopa').addEventListener('keyup', filterDropdownCopa);
        function filterDropdownCopa() {
            var inputSearch, filterText, ul, li, links, i, div;
            inputSearch = document.querySelector(".searchFieldCopa");
            filterText = inputSearch.value.toUpperCase();
            div = document.getElementById("dropdownCopa");
            links = div.getElementsByTagName("a");
            for (i = 0; i < links.length; i++) {
                txtValue = links[i].textContent || links[i].innerText;
                if (txtValue.toUpperCase().indexOf(filterText) > -1) {
                    links[i].style.display = "";
                } else {
                    links[i].style.display = "none";
                }
            }
        }

        document.querySelector('.searchFieldInternacional').addEventListener('keyup', filterDropdownInternacional);
        function filterDropdownInternacional() {
            var inputSearch, filterText, ul, li, links, i, div;
            inputSearch = document.querySelector(".searchFieldInternacional");
            filterText = inputSearch.value.toUpperCase();
            div = document.getElementById("dropdownInternacional");
            links = div.getElementsByTagName("a");
            for (i = 0; i < links.length; i++) {
                txtValue = links[i].textContent || links[i].innerText;
                if (txtValue.toUpperCase().indexOf(filterText) > -1) {
                    links[i].style.display = "";
                } else {
                    links[i].style.display = "none";
                }
            }
        }

        // JavaScript para manejar el colapso de otros menús
        document.querySelectorAll('.dropdown-toggle').forEach(function(element) {
            element.addEventListener('click', function(event) {
                var parent = element.closest('.dropdown');
                var menus = document.querySelectorAll('.dropdown-menu');
                menus.forEach(function(menu) {
                    if (menu !== parent.querySelector('.dropdown-menu')) {
                        menu.classList.remove('show');
                    }
                });
            });
        });
    </script>
</nav>
