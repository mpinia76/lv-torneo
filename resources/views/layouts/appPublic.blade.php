<!DOCTYPE html>
<html lang="en">
<head>
    @include('layouts.partials.meta')
</head>

<body>
<div class="wrapper">
@include('layouts.partials.headerPublic')

<div class="container">
    @yield('content')
</div>

@include('layouts.partials.footer')

@yield('scripts')
</div>
</body>
</html>
