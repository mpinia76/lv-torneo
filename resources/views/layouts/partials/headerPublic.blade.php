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

    /* Estilos del botón hamburguesa */
    .hamburger-menu {
        display: none;
        flex-direction: column;
        justify-content: space-around;
        width: 30px;
        height: 30px;
        cursor: pointer;
    }

    .hamburger-menu span {
        width: 100%;
        height: 3px;
        background-color: #333;
        border-radius: 2px;
        transition: 0.3s;
    }

    /* Menú móvil */
    .mobile-menu {
        display: none;
        position: absolute;
        top: 60px;
        right: 0;
        background-color: #fff;
        width: 100%;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }

    .mobile-menu ul {
        list-style-type: none;
        padding: 0;
        margin: 0;
    }

    .mobile-menu ul li {
        text-align: center;
        padding: 10px;
        border-bottom: 1px solid #ddd;
    }

    .mobile-menu ul li a {
        text-decoration: none;
        color: #333;
    }

    /* Mostrar el menú cuando esté activo */
    .mobile-menu.show {
        display: block;
    }
    .sub-dropdown-menu {
        display: none; /* Ocultar submenú por defecto */
        padding-left: 20px; /* Sangría para submenús */
    }
    #spanTorneo{
        display: none;
    }
    /* Mostrar el botón hamburguesa en pantallas pequeñas */
    @media (max-width: 768px) {
        .hamburger-menu {
            display: flex;
        }
        #spanTorneo{
            display: block;
        }
        #horizontalMenu {
            display: none; /* Oculta el menú horizontal en móviles */
        }
        .form-control {
            width: auto !important;
            height: auto !important;
            padding: 0 !important;
        }
        .h1, h1 {
            font-size: 20px;
        }
        .btn {

            padding: none !important;

            font-size: 8px !important;

        }
        .nav>li>a {
            padding: 1px 5px;
        }
    }
    .hamburger-menu.open span:nth-child(1) {
        transform: rotate(45deg) translate(5px, 5px);
    }

    .hamburger-menu.open span:nth-child(2) {
        opacity: 0;
    }

    .hamburger-menu.open span:nth-child(3) {
        transform: rotate(-45deg) translate(5px, -5px);
    }
#hamburgerSubMenu {
    float: right;
    margin: 2px;
}
</style>

<nav class="navbar navbar-default navbar-static-top">
    <div class="container">
        <!-- Botón de menú hamburguesa -->
        <div class="hamburger-menu" id="hamburgerMenu">
            <span></span>
            <span></span>
            <span></span>
        </div>

        <!-- Menú móvil -->
        <div class="mobile-menu" id="mobileMenu">
            <ul>
                <li><a class="dropdown-item" href="{{route('fechas.fixture')}}">Partidos</a></li>
                <li>
                    <a class="dropdown-item" href="#">Ligas nacionales <span class="toggle">+</span></a>
                    <ul class="sub-dropdown-menu" id="dropdownLigaMobile">

                        <input type="text" placeholder="Buscar.." class="searchFieldLigaMobile">
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
                <li>
                    <a class="dropdown-item" href="#">Copas nacionales <span class="toggle">+</span></a>
                    <ul class="sub-dropdown-menu" id="dropdownCopaMobile">

                        <input type="text" placeholder="Buscar.." class="searchFieldCopaMobile">
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
                <li>
                    <a class="dropdown-item" href="#">Internacionales <span class="toggle">+</span></a>
                    <ul class="sub-dropdown-menu" id="dropdownInternacionalMobile">

                        <input type="text" placeholder="Buscar.." class="searchFieldInternacionalMobile">
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
                <li>
                    <a class="dropdown-item" href="#">Protagonistas <span class="toggle">+</span></a>
                    <ul class="sub-dropdown-menu" role="menu">
                        <li><a class="dropdown-item" href="{{route('torneos.arqueros')}}">Arqueros</a></li>
                        <li><a class="dropdown-item" href="{{route('torneos.goleadores')}}">Goleadores</a></li>


                        <li><a class="dropdown-item" href="{{route('torneos.jugadores')}}">Jugadores</a></li>

                        <li><a class="dropdown-item" href="{{route('torneos.tarjetas')}}">Tarjetas</a></li>
                        <li><a class="dropdown-item" href="{{route('torneos.tecnicos')}}">Técnicos</a></li>

                    </ul>
                </li>
                <li>
                    <a class="dropdown-item" href="#">Equipos <span class="toggle">+</span></a>
                    <ul class="sub-dropdown-menu" role="menu">

                        <li><a class="dropdown-item" href="{{route('torneos.historiales')}}">Historiales</a></li>

                        <li><a class="dropdown-item" href="{{route('torneos.posiciones')}}">Tabla Histórica</a></li>

                        <li><a class="dropdown-item" href="{{route('torneos.titulos')}}">Títulos</a></li>
                    </ul>
                </li>
                <li><a class="dropdown-item" href="#">Estadísticas</a></li>
            </ul>
        </div>

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
        <!-- Menú horizontal -->
        @if(Session::has('codigoTorneo'))
            <div class="hamburger-menu" id="hamburgerSubMenu">
                <span></span>
                <span></span>
                <span></span>

            </div>
            <span id="spanTorneo" style="background-color: #ccf1cd;"><a href="#" style="color: #4ea3e7;font-weight: bold;">@if(Session::get('escudoTorneo'))
                        <img id="original" src="{{ url('images/'.Session::get('escudoTorneo')) }}" height="25">
                    @endif{{Session::get('nombreTorneo')}}</a></span>
            <!-- Menú móvil -->
            <div class="mobile-menu" id="mobileSubMenu">
                <ul>

                    <li><a class="dropdown-item" href="{{route('fechas.ver',  array('torneoId' => Session::get('codigoTorneo')))}}">Fixture</a></li>
                    @if(Session::has('sessionPosiciones'))
                        <li class="dropdown">

                            <a class="dropdown-item" href="#">Tablas <span class="toggle">+</span></a>

                            <ul class="sub-dropdown-menu" role="menu" style="display: none">
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

                        <a class="dropdown-item" href="#">{{ __('Protagonistas') }} <span class="toggle">+</span></a>
                        <ul class="sub-dropdown-menu" role="menu" style="display: none">
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
    </div>



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
        document.addEventListener('DOMContentLoaded', function () {
            const hamburger = document.getElementById('hamburgerMenu');
            const menu = document.getElementById('mobileMenu');

            hamburger.addEventListener('click', function () {
                // Alternar la visibilidad del menú
                if (menu.style.display === 'block') {
                    menu.style.display = 'none';
                } else {
                    menu.style.display = 'block';
                }
            });

            const hamburgerSub = document.getElementById('hamburgerSubMenu');
            const menuSub = document.getElementById('mobileSubMenu');
            // Verifica si el elemento existe antes de agregar el event listener
            if (hamburgerSub && menuSub) {
                hamburgerSub.addEventListener('click', function () {
                    // Alternar la visibilidad del menú
                    if (menuSub.style.display === 'block') {
                        menuSub.style.display = 'none';
                    } else {
                        menuSub.style.display = 'block';
                    }
                });
            }

            const dropdownItems = document.querySelectorAll('.dropdown-item');

            dropdownItems.forEach(item => {
                item.addEventListener('click', function (e) {
                    // Prevenir la navegación si hay un submenú
                    const subMenu = this.nextElementSibling;
                    if (subMenu && subMenu.classList.contains('sub-dropdown-menu')) {
                        e.preventDefault(); // Prevenir la acción predeterminada del enlace

                        // Alternar visibilidad del submenú
                        if (subMenu.style.display === 'block') {
                            subMenu.style.display = 'none';
                            this.querySelector('.toggle').textContent = '+'; // Cambiar a "+"
                        } else {
                            subMenu.style.display = 'block';
                            this.querySelector('.toggle').textContent = '-'; // Cambiar a "-"
                        }
                    }
                });
            });
        });
        document.querySelector('.searchFieldLigaMobile').addEventListener('keyup', filterDropdownLigaMobile);
        function filterDropdownLigaMobile() {
            var inputSearch, filterText, ul, li, links, i, div;
            inputSearch = document.querySelector(".searchFieldLigaMobile");
            filterText = inputSearch.value.toUpperCase();
            div = document.getElementById("dropdownLigaMobile");
            links = div.getElementsByTagName("a");
            for (i = 0; i < links.length; i++) {
                txtValue = links[i].textContent || links[i].innerText;
                if (txtValue.toUpperCase().indexOf(filterText) > -1) {
                    links[i].parentElement.style.display = ""; // Mostrar el elemento padre <li>
                } else {
                    links[i].parentElement.style.display = "none"; // Ocultar el elemento padre <li>
                }
            }
        }

        document.querySelector('.searchFieldCopaMobile').addEventListener('keyup', filterDropdownCopaMobile);
        function filterDropdownCopaMobile() {
            var inputSearch, filterText, ul, li, links, i, div;
            inputSearch = document.querySelector(".searchFieldCopaMobile");
            filterText = inputSearch.value.toUpperCase();
            div = document.getElementById("dropdownCopaMobile");
            links = div.getElementsByTagName("a");
            for (i = 0; i < links.length; i++) {
                txtValue = links[i].textContent || links[i].innerText;
                if (txtValue.toUpperCase().indexOf(filterText) > -1) {
                    links[i].parentElement.style.display = ""; // Mostrar el elemento padre <li>
                } else {
                    links[i].parentElement.style.display = "none"; // Ocultar el elemento padre <li>
                }
            }
        }

        document.querySelector('.searchFieldInternacionalMobile').addEventListener('keyup', filterDropdownInternacionalMobile);
        function filterDropdownInternacionalMobile() {
            var inputSearch, filterText, ul, li, links, i, div;
            inputSearch = document.querySelector(".searchFieldInternacionalMobile");
            filterText = inputSearch.value.toUpperCase();
            div = document.getElementById("dropdownInternacionalMobile");
            links = div.getElementsByTagName("a");
            for (i = 0; i < links.length; i++) {
                txtValue = links[i].textContent || links[i].innerText;
                if (txtValue.toUpperCase().indexOf(filterText) > -1) {
                    links[i].parentElement.style.display = ""; // Mostrar el elemento padre <li>
                } else {
                    links[i].parentElement.style.display = "none"; // Ocultar el elemento padre <li>
                }
            }
        }

    </script>

</nav>
