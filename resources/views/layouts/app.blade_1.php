<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo csrf_token() ?>"/>

    <title>{{ config('app.name', 'Torneos') }}</title>

    <!-- Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.5.0/css/font-awesome.min.css" integrity="sha384-XdYbMnZ/QjLh6iI4ogqCTaIjrFk87ip+ekIjefZch0Y+PvJ8CDYtEs1ipDmPorQ+" crossorigin="anonymous">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Lato:100,300,400,700">

    <!-- Styles -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.6/css/bootstrap.min.css" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css" rel="stylesheet" />

    {{-- <link href="{{ elixir('css/app.css') }}" rel="stylesheet"> --}}
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link rel="shortcut icon" type="image/png" href="{{ url('images/icon_ball.png') }}">
    <style>
        body {
            font-family: 'Lato';
        }

        .fa-btn {
            margin-right: 6px;
        }
    </style>
</head>
<body id="app-layout">
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
            <a class="navbar-brand" href="{{ url('/admin') }}">
                Torneos
            </a>
        </div>

        <div class="collapse navbar-collapse" id="app-navbar-collapse">
            <!-- Left Side Of Navbar -->
            <ul class="nav navbar-nav navbar-left">

                <li class="dropdown">
                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">{{ __('Administracion') }} <span class="caret"></span></a>
                    <ul class="dropdown-menu" role="menu">
                        <li><a class="dropdown-item" href="{{route('arbitros.index')}}">
                                {{ __('Arbitros') }}
                            </a>
                        </li>
                        <li><a class="dropdown-item" href="{{route('equipos.index')}}">
                                {{ __('Equipos') }}
                            </a>
                        </li>

                        <li><a class="dropdown-item" href="{{route('jugadores.index')}}">
                                {{ __('Jugadores') }}
                            </a>
                        </li>
                        <li><a class="dropdown-item" href="{{route('tecnicos.index')}}">
                                {{ __('Tecnicos') }}
                            </a>
                        </li>
                        <li><a class="dropdown-item" href="{{route('torneos.index')}}">
                                {{ __('Torneos') }}
                            </a>
                        </li>




                    </ul>

                </li>
            </ul>



            <!-- Right Side Of Navbar -->
            <ul class="nav navbar-nav navbar-right">
                <!-- Authentication Links -->
                @if (Auth::guest())
                    <li><a href="{{ url('/login') }}">Login</a></li>
                    <li><a href="{{ url('/register') }}">Register</a></li>
                @else
                    <li class="dropdown">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false">
                            {{ Auth::user()->name }} <span class="caret"></span>
                        </a>

                        <ul class="dropdown-menu" role="menu">
                            <li><a href="{{ url('/logout') }}"><i class="fa fa-btn fa-sign-out"></i>Logout</a></li>
                        </ul>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</nav>


@yield('content')

<!-- JavaScripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.2.3/jquery.min.js" integrity="sha384-I6F5OKECLVtK/BL+8iSLDEHowSAfUo76ZL9+kGAgTRdiByINKJaqTPH/QVNS1VDb" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>



<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js"></script>
<script src="{{asset('ini.js')}}"></script>


@yield('bottom')

<script>
    function baseUrl(url) {
        return '{{url('')}}/' + url;
    }

        $(document).ready(function() {

        $('.js-example-basic-single').select2();
    });
    $('.addRow').on('click',function(e){
        e.preventDefault();
        addRow();
    });
    function addRow()
    {
        var tr='<tr>'+
            '<td></td><td>'+'{{ Form::select('jugador[]',$jugadors ?? [''=>''], '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}'+'</td>'+
    '<td>'+'{{Form::number('dorsal[]', '', ['class' => 'form-control', 'size' => '4'])}}'+'</td>'+

    '<td><a href="#" class="btn btn-danger remove"><i class="glyphicon glyphicon-remove"></i></a></td>'+
    '</tr>';
        $('#cuerpoJugador').append(tr);
        $('.js-example-basic-single').select2();
    };

   $('body').on('click', '.remove', function(e){

        e.preventDefault();
        var last=$('tbody tr').length;
        if(last==1){
            alert("No se puede borrar");
        }
        else{
            $(this).parent().parent().remove();
        }

    });

    $('.addRowTecnico').on('click',function(e){
        e.preventDefault();
        addRowTecnico();
    });
    function addRowTecnico()
    {
        var tr='<tr>'+
            '<td></td><td>'+'{{ Form::select('tecnico[]',$tecnicos ?? [''=>''], '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}'+'</td>'+


            '<td><a href="#" class="btn btn-danger removeTecnico"><i class="glyphicon glyphicon-remove"></i></a></td>'+
            '</tr>';
        $('#cuerpoTecnico').append(tr);
        $('.js-example-basic-single').select2();
    };
    $('body').on('click', '.removeTecnico', function(e){
        e.preventDefault();

         $(this).parent().parent().remove();


    });

    $('.addRowGol').on('click',function(e){
        e.preventDefault();
        addRowGol();
    });
    function addRowGol()
    {
        var tr='<tr>'+
            '<td></td><td>'+'{{ Form::select('jugador[]',$jugadors ?? [''=>''], '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}'+'</td>'+
            '<td>'+'{{Form::number('minuto[]', '', ['class' => 'form-control', 'style' => 'width:70px;'])}}'+'</td>'+
            '<td>'+'{{ Form::select('tipo[]',['Cabeza'=>'Cabeza','En Contra'=>'En Contra','Jugada'=>'Jugada','Penal'=>'Penal','Tiro Libre'=>'Tiro Libre'], '',['class' => 'form-control']) }}'+'</td>'+
            '<td><a href="#" class="btn btn-danger removegol"><i class="glyphicon glyphicon-remove"></i></a></td>'+
            '</tr>';
        $('#cuerpogol').append(tr);
        $('.js-example-basic-single').select2();
    };
   $('body').on('click', '.removegol', function(e){
            e.preventDefault();
        var last=$('tbody tr').length;
        if(last==1){
            alert("No se puede borrar");
        }
        else{
            $(this).parent().parent().remove();
        }

    });
    $('.addRowTarjeta').on('click',function(e){
        e.preventDefault();
        addRowTarjeta();
    });
    function addRowTarjeta()
    {
        var tr='<tr>'+
            '<td></td><td>'+'{{ Form::select('jugador[]',$jugadors ?? [''=>''], '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}'+'</td>'+
            '<td>'+'{{Form::number('minuto[]', '', ['class' => 'form-control', 'style' => 'width:70px;'])}}'+'</td>'+
            '<td>'+'{{ Form::select('tipo[]',['Amarilla'=>'Amarilla','Doble Amarilla'=>'Doble Amarilla','Roja'=>'Roja'], '',['class' => 'form-control']) }}'+'</td>'+
            '<td><a href="#" class="btn btn-danger removetarjeta"><i class="glyphicon glyphicon-remove"></i></a></td>'+
            '</tr>';
        $('#cuerpotarjeta').append(tr);
        $('.js-example-basic-single').select2();
    };
   $('body').on('click', '.removetarjeta', function(e){

        e.preventDefault();
        var last=$('tbody tr').length;
        if(last==1){
            alert("No se puede borrar");
        }
        else{
            $(this).parent().parent().remove();
        }

    });
    $('.addRowArbitro').on('click',function(e){
        e.preventDefault();
        addRowArbitro();
    });
    function addRowArbitro()
    {
        var tr='<tr>'+
            '<td></td><td>'+'{{ Form::select('arbitro[]',$arbitros ?? [''=>''], '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}'+'</td>'+

            '<td>'+'{{ Form::select('tipo[]',['Principal'=>'Principal','Linea 1'=>'Linea 1','Linea 2'=>'Linea 2','Cuarto'=>'Cuarto','VAR'=>'VAR']) }}'+'</td>'+
            '<td><a href="#" class="btn btn-danger removearbitro"><i class="glyphicon glyphicon-remove"></i></a></td>'+
            '</tr>';
        $('#cuerpoarbitro').append(tr);
        $('.js-example-basic-single').select2();
    };
    $('body').on('click', '.removearbitro', function(e){

        e.preventDefault();
        var last=$('tbody tr').length;
        if(last==1){
            alert("No se puede borrar");
        }
        else{
            $(this).parent().parent().remove();
        }

    });

    $('.addRowTitularL').on('click',function(e){
        e.preventDefault();
        addRowTitularL();
    });

    function addRowTitularL()
    {

        var tr='<tr>'+
            '<td></td><td>'+'{{ Form::select('titularl[]',$jugadorsL ?? [''=>''], '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}'+'</td>'+
            '<td>'+'{{Form::number('dorsaltitularl[]', '', ['class' => 'form-control', 'style' => 'width:70px;'])}}'+'</td>'+

            '<td><a href="#" class="btn btn-danger removetitularl"><i class="glyphicon glyphicon-remove"></i></a></td>'+
            '</tr>';
        $('#cuerpotitularlocal').append(tr);
        $('.js-example-basic-single').select2();
    };
    $('body').on('click', '.removetitularl', function(e){

        e.preventDefault();
        var last=$('tbody tr').length;
        if(last==1){
            alert("No se puede borrar");
        }
        else{
            $(this).parent().parent().remove();
        }

    });

    $('.addRowSuplenteL').on('click',function(e){
        e.preventDefault();
        addRowSuplenteL();
    });

    function addRowSuplenteL()
    {
        var tr='<tr>'+
            '<td></td><td>'+'{{ Form::select('suplentel[]',$jugadorsL ?? [''=>''], '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}'+'</td>'+
            '<td>'+'{{Form::number('dorsalsuplentel[]', '', ['class' => 'form-control', 'style' => 'width:70px;'])}}'+'</td>'+

            '<td><a href="#" class="btn btn-danger removesuplentel"><i class="glyphicon glyphicon-remove"></i></a></td>'+
            '</tr>';
        $('#cuerposuplentelocal').append(tr);
        $('.js-example-basic-single').select2();
    };
    $('body').on('click', '.removesuplentel', function(e){

        e.preventDefault();
        var last=$('tbody tr').length;
        if(last==1){
            alert("No se puede borrar");
        }
        else{
            $(this).parent().parent().remove();
        }

    });

    $('.addRowTitularV').on('click',function(e){
        e.preventDefault();
        addRowTitularV();
    });

    function addRowTitularV()
    {
        var tr='<tr>'+
            '<td></td><td>'+'{{ Form::select('titularv[]',$jugadorsV ?? [''=>''], '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}'+'</td>'+
            '<td>'+'{{Form::number('dorsaltitularv[]', '', ['class' => 'form-control', 'style' => 'width:70px;'])}}'+'</td>'+

            '<td><a href="#" class="btn btn-danger removetitularv"><i class="glyphicon glyphicon-remove"></i></a></td>'+
            '</tr>';
        $('#cuerpotitularvisitante').append(tr);
        $('.js-example-basic-single').select2();
    };
    $('body').on('click', '.removetitularv', function(e){

        e.preventDefault();
        var last=$('tbody tr').length;
        if(last==1){
            alert("No se puede borrar");
        }
        else{
            $(this).parent().parent().remove();
        }

    });

    $('.addRowSuplenteV').on('click',function(e){
        e.preventDefault();
        addRowSuplenteV();
    });

    function addRowSuplenteV()
    {
        var tr='<tr>'+
            '<td></td><td>'+'{{ Form::select('suplentev[]',$jugadorsV ?? [''=>''], '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}'+'</td>'+
            '<td>'+'{{Form::number('dorsalsuplentev[]', '', ['class' => 'form-control', 'style' => 'width:70px;'])}}'+'</td>'+

            '<td><a href="#" class="btn btn-danger removesuplentev"><i class="glyphicon glyphicon-remove"></i></a></td>'+
            '</tr>';
        $('#cuerposuplentevisitante').append(tr);
        $('.js-example-basic-single').select2();
    };
    $('body').on('click', '.removesuplentev', function(e){

        e.preventDefault();
        var last=$('tbody tr').length;
        if(last==1){
            alert("No se puede borrar");
        }
        else{
            $(this).parent().parent().remove();
        }

    });

    $('.addRowCambio').on('click',function(e){
        e.preventDefault();
        addRowCambio();
    });
    function addRowCambio()
    {
        var tr='<tr>'+
            '<td></td><td>'+'{{ Form::select('jugador[]',$jugadors ?? [''=>''], '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}'+'</td>'+
            '<td>'+'{{ Form::select('tipo[]',['Entra'=>'Entra','Sale'=>'Sale'], '',['class' => 'form-control']) }}'+'</td>'+
            '<td>'+'{{Form::number('minuto[]', '', ['class' => 'form-control', 'style' => 'width:70px;'])}}'+'</td>'+

            '<td><a href="#" class="btn btn-danger removecambio"><i class="glyphicon glyphicon-remove"></i></a></td>'+
            '</tr>';
        $('#cuerpocambio').append(tr);
        $('.js-example-basic-single').select2();
    };
    $('body').on('click', '.removecambio', function(e){

        e.preventDefault();
        var last=$('tbody tr').length;
        if(last==1){
            alert("No se puede borrar");
        }
        else{
            $(this).parent().parent().remove();
        }

    });

</script>

{{-- <script src="{{ elixir('js/app.js') }}"></script> --}}
</body>
</html>
