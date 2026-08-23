{{-- Pie --}}
<footer class="t-pie mt-auto">
    <div class="container t-pie-inner">
        <div>&copy; {{ date('Y') }} Todos los derechos reservados.</div>
        <!--<div>
            <a href="#" class="me-3">Aviso Legal</a>
            <a href="#" class="me-3">Privacidad</a>
            <a href="#">Contacto</a>
        </div>-->
    </div>
</footer>

{{-- Librerías --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.2.3/jquery.min.js" integrity="sha384-I6F5OKECLVtK/BL+8iSLDEHowSAfUo76ZL9+kGAgTRdiByINKJaqTPH/QVNS1VDb" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js"></script>
<script src="{{ asset('ini.js') }}"></script>
<script src="{{ asset('js/dropdownFilter.js') }}"></script>
<script src="{{ asset('js/torneos.js') }}?v=1"></script>

@yield('bottom')

<script>
    function baseUrl(url) {
        return '{{ url('') }}/' + url;
    }

    $(document).ready(function () {
        $('.js-example-basic-single').select2();
    });
</script>
