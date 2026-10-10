{{-- Pie --}}
<footer class="t-pie mt-auto">
    <div class="container t-pie-inner">
        <div>&copy; {{ date('Y') }} {{ __('Todos los derechos reservados.') }}</div>
        <!--<div>
            <a href="#" class="me-3">Aviso Legal</a>
            <a href="#" class="me-3">Privacidad</a>
            <a href="#">Contacto</a>
        </div>-->
    </div>
</footer>

{{-- Librerías --}}
{{-- Mismo archivo que el de cdnjs (sha384 idéntico), servido desde public/vendor/ --}}
<script src="{{ asset('vendor/jquery-2.2.3/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/bootstrap-5.3.3/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/select2-4.0.6-rc.0/select2.min.js') }}"></script>
<script src="{{ asset('ini.js') }}"></script>
<script src="{{ asset('js/dropdownFilter.js') }}"></script>
{{-- Textos de torneos.js en el idioma de la página (en español no hace falta) --}}
<script>window.TRAD = @json(app()->getLocale() === 'es' ? new stdClass : textos_js());</script>
<script src="{{ asset('js/torneos.js') }}?v=10"></script>

@yield('bottom')

<script>
    function baseUrl(url) {
        return '{{ url('') }}/' + url;
    }

    $(document).ready(function () {
        $('.js-example-basic-single').select2();
    });
</script>
