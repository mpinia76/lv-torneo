<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>@hasSection('pageTitle')@yield('pageTitle') · @endif{{ config('app.name', 'Torneos') }}</title>

{{-- Tema elegido, antes de pintar, para que no parpadee --}}
<script>
    (function () {
        try {
            var t = localStorage.getItem('tema');
            if (t !== 'dark' && t !== 'light') {
                t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-bs-theme', t);
        } catch (e) {
            document.documentElement.setAttribute('data-bs-theme', 'light');
        }
    })();
</script>

{{-- Tipografías --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800&family=IBM+Plex+Mono:wght@400;500&family=Instrument+Sans:wght@400;500;600&display=swap" rel="stylesheet">

{{-- Bootstrap 5.3 + iconos --}}
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/css/select2.min.css" rel="stylesheet">

{{-- Sistema visual del sitio (siempre después de Bootstrap) --}}
<link href="{{ asset('css/torneos.css') }}?v=10" rel="stylesheet">

<link rel="shortcut icon" type="image/png" href="{{ url('images/icon_ball.png') }}">
