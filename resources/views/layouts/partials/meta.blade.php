<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?php echo csrf_token() ?>"/>

<title>{{ config('app.name', 'Torneos') }}</title>

<!-- Fonts -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.5.0/css/font-awesome.min.css" integrity="sha384-XdYbMnZ/QjLh6iI4ogqCTaIjrFk87ip+ekIjefZch0Y+PvJ8CDYtEs1ipDmPorQ+" crossorigin="anonymous">
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Lato:100,300,400,700">

<!-- Styles -->
<!--<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.6/css/bootstrap.min.css" integrity="sha384-1q8mTJOASx8j1Au+a5WDVnPi2lkFfwwEAa8hDDdjZlpLegxhjVME1fgjWPGmkzs7" crossorigin="anonymous">-->
{{-- Bootstrap CSS --}}
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css" rel="stylesheet" />

{{-- <link href="{{ elixir('css/app.css') }}" rel="stylesheet"> --}}
<link href="{{ asset('css/app.css') }}" rel="stylesheet">
<link href="{{asset('css/components.min.css')}}" rel="stylesheet" type="text/css">
<link rel="shortcut icon" type="image/png" href="{{ url('images/icon_ball.png') }}">
<style>
    body {
        font-family: 'Lato';
    }

    .fa-btn {
        margin-right: 6px;
    }
    .load{
        position: fixed;
        z-index: 9999;
        width: 100%;
        height: 100%;
    }
    .load .in{
        width: 400px;
        text-align: center;
        margin-right: auto;
        margin-left: auto;
        margin-top: 10%;
    }
    .wrapper {
        filter: blur(3px);
    }
    img {
        vertical-align: middle;
    }
    img {
        border: 0;
    }



</style>
<div class="load">
    <div class="in"><img width="20%" src="{{ url('/images/hourglass.svg') }}"></div>
</div>

