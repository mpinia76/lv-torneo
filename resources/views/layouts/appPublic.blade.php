<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('layouts.partials.metaPublic')
</head>
<body class="d-flex flex-column min-vh-100">

    @include('layouts.partials.headerPublic')

    <main class="container flex-grow-1">
        @yield('content')
    </main>

    @include('layouts.partials.footerPublic')

    @yield('scripts')

</body>
</html>
