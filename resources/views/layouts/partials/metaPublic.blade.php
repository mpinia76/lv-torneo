<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

{{-- Paginas internas: «Sujeto — que encuentra | La Planilla». Portada: marca + bajada. --}}
@php
    // Título, descripción e imagen de la página. Las vistas los definen con
    // @section('pageTitle' / 'pageDescription' / 'pageImage'); lo que llega de una
    // sección ya viene escapado (Blade aplica e() al guardarla), así que todo se
    // arma como HTML seguro y se imprime con {!! !!}.
    $mpMarca  = e(config('app.name', 'La Planilla'));
    $mpTitulo = trim($__env->yieldContent('pageTitle'));
    $mpTitulo = $mpTitulo !== ''
        ? $mpTitulo . ' | ' . $mpMarca
        : $mpMarca . ' — ' . e(__('todo el fútbol del siglo XXI'));
    $mpDesc = trim($__env->yieldContent('pageDescription'));
    if ($mpDesc === '') {
        $mpDesc = e(__('Fichas completas de jugadores, directores técnicos y equipos: cada partido oficial del siglo XXI, con formaciones, goles, tarjetas, cambios y penales.'));
    }
    // Imagen para compartir: la foto o el escudo de la ficha, si hay; si no, la
    // tarjeta de la marca (1200x630, public/og/) en el idioma de la página.
    $mpImagen  = trim($__env->yieldContent('pageImage'));
    $mpPropia  = $mpImagen !== '';
    if (!$mpPropia) {
        $mpImagen = e(url('og/laplanilla-' . (app()->getLocale() === 'en' ? 'en' : 'es') . '.png'));
    }
    $mpLocales = ['es' => 'es_AR', 'en' => 'en_US'];
@endphp
<title>{!! $mpTitulo !!}</title>
<meta name="description" content="{!! $mpDesc !!}">

@hasSection('robots')
<meta name="robots" content="@yield('robots')">
@endif

{{-- Versión oficial de esta página (sin orden, filtros ni campañas): ver query_canonica() --}}
<link rel="canonical" href="{{ url_canonica() }}">

{{-- Vista previa al compartir (WhatsApp, Facebook, X, Telegram…) --}}
<meta property="og:site_name" content="{!! $mpMarca !!}">
<meta property="og:type" content="website">
<meta property="og:title" content="{!! $mpTitulo !!}">
<meta property="og:description" content="{!! $mpDesc !!}">
<meta property="og:url" content="{{ url_canonica() }}">
<meta property="og:image" content="{!! $mpImagen !!}">
@unless($mpPropia)
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
@endunless
<meta property="og:locale" content="{{ $mpLocales[app()->getLocale()] ?? 'es_AR' }}">
@foreach($mpLocales as $mpCod => $mpLoc)
@if($mpCod !== app()->getLocale())
<meta property="og:locale:alternate" content="{{ $mpLoc }}">
@endif
@endforeach
{{-- Foto o escudo (chicos y cuadrados): tarjeta chica. La de la marca: grande. --}}
<meta name="twitter:card" content="{{ $mpPropia ? 'summary' : 'summary_large_image' }}">

{{-- La misma página en cada idioma, para los buscadores --}}
@foreach(idiomas_sitio() as $codIdioma => $nomIdioma)
<link rel="alternate" hreflang="{{ $codIdioma }}" href="{{ url_idioma($codIdioma, true) }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ url_idioma(array_keys(idiomas_sitio())[0], true) }}">

{{-- Datos estructurados (schema.org) que haya cargado la vista: ver datos_estructurados() --}}
{!! datos_estructurados_html() !!}

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

{{-- Todo lo que el navegador necesita para pintar sale de este mismo servidor (antes venía
     de Google Fonts, jsdelivr y cdnjs: con 4G lenta cada servidor nuevo costaba una conexión
     entera antes del primer pintado). Las versiones van en el nombre de la carpeta de
     public/vendor/: para actualizar, carpeta nueva. --}}

{{-- Tipografías: sin preload a propósito (font-display:swap). Con preload, en 4G lenta
     competían con bootstrap.min.css y atrasaban el primer pintado; así el texto sale con
     la fuente del sistema y cambia cuando llegan. --}}
@include('layouts.partials.fuentes')

{{-- Bootstrap 5.3 (bloquea el pintado a propósito: sin él la página sale desarmada) --}}
<link href="{{ asset('vendor/bootstrap-5.3.3/bootstrap.min.css') }}" rel="stylesheet">

{{-- Íconos y Select2: no hacen falta para el primer pintado, se cargan sin frenarlo --}}
{{-- Íconos: bootstrap-icons-1.11.1-sitio es un recorte (28 íconos, 3 KB en vez de 131 KB) armado
     con fontTools desde bootstrap-icons-1.11.1/. Si una vista usa un bi-* nuevo, no se va a ver
     hasta regenerar el recorte (buscar todos los bi-* en resources/views, public/js y app). --}}
<link href="{{ asset('vendor/bootstrap-icons-1.11.1-sitio/bootstrap-icons.min.css') }}" rel="stylesheet" media="print" onload="this.media='all'">
<link href="{{ asset('vendor/select2-4.0.6-rc.0/select2.min.css') }}" rel="stylesheet" media="print" onload="this.media='all'">
<noscript>
<link href="{{ asset('vendor/bootstrap-icons-1.11.1-sitio/bootstrap-icons.min.css') }}" rel="stylesheet">
<link href="{{ asset('vendor/select2-4.0.6-rc.0/select2.min.css') }}" rel="stylesheet">
</noscript>

{{-- Sistema visual del sitio (siempre después de Bootstrap) --}}
<link href="{{ asset('css/torneos.css') }}?v=25" rel="stylesheet">

{{-- Favicon: el .ico es para lo que no lee SVG (Safari viejo, lectores de
     feeds, el pedido automático a /favicon.ico); el SVG gana donde se entiende.
     Los tres salen del mismo dibujo: si cambia el SVG, regenerar los otros dos. --}}
<link rel="icon" href="{{ url('favicon.ico') }}?v=2" sizes="any">
<link rel="icon" type="image/svg+xml" href="{{ url('favicon.svg') }}?v=2">
<link rel="apple-touch-icon" href="{{ url('apple-touch-icon.png') }}?v=2">

{{-- Google Analytics 4. Va en el <head>, que sale de la caché de páginas: no
     depende de quién mira, así que no se puede condicionar por sesión acá. --}}
@if(config('services.google_analytics.id'))
<script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google_analytics.id') }}"></script>
<script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '{{ config('services.google_analytics.id') }}');
</script>
@endif
