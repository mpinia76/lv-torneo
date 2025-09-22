{{-- Footer --}}
<footer class="bg-dark text-white mt-auto py-3">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center">
        <div>
            &copy; {{ date('Y') }} Todos los derechos reservados.
        </div>
        <!--<div>
            <a href="#" class="text-white text-decoration-none me-3">Aviso Legal</a>
            <a href="#" class="text-white text-decoration-none me-3">Privacidad</a>
            <a href="#" class="text-white text-decoration-none">Contacto</a>
        </div>-->
    </div>
</footer>
<!-- JavaScripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.2.3/jquery.min.js" integrity="sha384-I6F5OKECLVtK/BL+8iSLDEHowSAfUo76ZL9+kGAgTRdiByINKJaqTPH/QVNS1VDb" crossorigin="anonymous"></script>
<!--<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>-->
{{-- Bootstrap JS --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>



<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js"></script>
<script src="{{asset('ini.js')}}"></script>
<script src="{{ asset('js/dropdownFilter.js') }}"></script>


@yield('bottom')

<script>
    $(window).on('load',function(){

        $('.load').hide();
        $('.wrapper').css('filter','blur(0)');

    });
    function baseUrl(url) {
        return '{{url('')}}/' + url;
    }


</script>
