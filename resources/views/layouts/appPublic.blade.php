<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.meta')
</head>
<body class="d-flex flex-column min-vh-100">
<div class="wrapper d-flex flex-column flex-grow-1">

    @include('layouts.partials.headerPublic')

    <main class="container flex-grow-1">
        @yield('content')
    </main>

    @include('layouts.partials.footer')

    @yield('scripts')

</div>
</body>
</html>
