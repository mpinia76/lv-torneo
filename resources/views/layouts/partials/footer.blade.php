<!-- JavaScripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.2.3/jquery.min.js" integrity="sha384-I6F5OKECLVtK/BL+8iSLDEHowSAfUo76ZL9+kGAgTRdiByINKJaqTPH/QVNS1VDb" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>



<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js"></script>
<script src="{{asset('ini.js')}}"></script>


@yield('bottom')

<script>
    $(window).on('load',function(){

        $('.load').hide();
        $('.wrapper').css('filter','blur(0)');

    });
    function baseUrl(url) {
        return '{{url('')}}/' + url;
    }
    var CSRF_TOKEN = $('meta[name="csrf-token"]').attr('content');
    $(document).ready(function() {
        $('#seleccionarTodosControlarPlantillas').click(function () {

            $('input[name="jugador_ids[]"]').prop('checked', true);
        });
        $('.js-example-basic-single').select2();
            @if(isset($i))
            @for ($j =1; $j <= $i; $j++)
            $('#jugador{{$j}}').select2({

                minimumInputLength: 3,
                ajax: {
                    url: '{{ route("plantilla.search") }}',
                    type: "get",
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            _token: CSRF_TOKEN,
                            search: params.term // search term
                        };
                    },
                    processResults: function (response) {
                        return {
                            results: response
                        };
                    },
                    cache: true
                }

            }
        ).on('change', function (e) {

            // Obtener el valor seleccionado
            var selectedValue = e.target.value;

            // Obtener el elemento td anterior
            var tdWithImage = $(this).closest('tr').find('td').eq(0); // Primer td en la misma fila

            // Obtener la URL de la foto del jugador desde la respuesta JSON
            var fotoURL = $(this).select2('data')[0].foto;


            // Actualizar la imagen en el td anterior con la URL de la foto
            tdWithImage.find('img').attr('src', fotoURL);
        });
        @endfor
@endif
        $('#reasignarId').select2({

                minimumInputLength: 3,
                ajax: {
                    url: '{{ route("plantilla.search") }}',
                    type: "get",
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            _token: CSRF_TOKEN,
                            search: params.term // search term
                        };
                    },
                    processResults: function (response) {
                        return {
                            results: response
                        };
                    },
                    cache: true
                }

            }
        ).on('change', function (e) {

            // Obtener el valor seleccionado
            var selectedValue = e.target.value;

            // Obtener el elemento td anterior
            var tdWithImage = $(this).closest('tr').find('td').eq(0); // Primer td en la misma fila

            // Obtener la URL de la foto del jugador desde la respuesta JSON
            var fotoURL = $(this).select2('data')[0].foto;


            // Actualizar la imagen en el td anterior con la URL de la foto
            tdWithImage.find('img').attr('src', fotoURL);
        });


    });



    $('.addRow').on('click',function(e){
        e.preventDefault();
        addRow();
    });
    function addRow()
    {

        var cantJugadores = parseInt($("#cantJugadors").val()) + 2;

        $("#cantJugadors").val(cantJugadores);
        var tr='<tr>'+
            '<td></td><td>'+'<select class="form-control js-example-basic-single" style= "width:300px;" id="jugador'+cantJugadores+'" name="jugador[]"></select>'+'</td>'+
            '<td>'+'{{Form::number('dorsal[]', '', ['class' => 'form-control', 'style' => 'width:70px;'])}}'+'</td>'+

            '<td><a href="#" class="btn btn-danger remove"><i class="glyphicon glyphicon-remove"></i></a></td>'+
            '</tr>';
        $('#cuerpoJugador').append(tr);
        //$('.js-example-basic-single').select2();
        $('#jugador'+cantJugadores).select2({

                minimumInputLength: 3,
                ajax: {
                    url: '{{ route("plantilla.search") }}',
                    type: "get",
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            _token: CSRF_TOKEN,
                            search: params.term // search term
                        };
                    },
                    processResults: function (response) {
                        return {
                            results: response
                        };
                    },
                    cache: true
                }
            }
        ).on('change', function (e) {

            // Obtener el valor seleccionado
            var selectedValue = e.target.value;
            console.log(selectedValue);
            // Obtener el elemento td anterior
            var tdWithImage = $(this).closest('tr').find('td').eq(0); // Primer td en la misma fila
            console.log(tdWithImage);
            // Obtener la URL de la foto del jugador desde la respuesta JSON
            var fotoURL = $(this).select2('data')[0].foto;
            // Verificar si ya hay una imagen en el <td>
            var existingImg = tdWithImage.find('img');

            if (existingImg.length > 0) {
                // Si ya existe una imagen, reemplazarla
                existingImg.attr('src', fotoURL);
            } else {
                var img = document.createElement('img');
                img.id = 'original';
                img.className = 'imgCircle';
                img.src = fotoURL;
                // Crear un elemento de texto para el número
                //var numeroText = document.createTextNode(cantJugadores-1); // Cambia '1' al número que desees

                // Insertar el número antes de la imagen en el <td>
                //tdWithImage.prepend(numeroText);
                tdWithImage.append(img);
                // Actualizar la imagen en el td anterior con la URL de la foto
                //tdWithImage.find('img').attr('src', fotoURL);
            }
        });
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

    $('.addRowTecnicoL').on('click',function(e){
        e.preventDefault();
        addRowTecnicoL();
    });
    function addRowTecnicoL()
    {
        var tr='<tr>'+
            '<td></td><td>'+'{{ Form::select('tecnicoL[]',$tecnicos ?? [''=>''], '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}'+'</td>'+


            '<td><a href="#" class="btn btn-danger removeTecnicoL"><i class="glyphicon glyphicon-remove"></i></a></td>'+
            '</tr>';
        $('#cuerpoTecnicoL').append(tr);
        $('.js-example-basic-single').select2();
    };
    $('body').on('click', '.removeTecnicoL', function(e){
        e.preventDefault();

        $(this).parent().parent().remove();


    });

    $('.addRowTecnicoV').on('click',function(e){
        e.preventDefault();
        addRowTecnicoV();
    });
    function addRowTecnicoV()
    {
        var tr='<tr>'+
            '<td></td><td>'+'{{ Form::select('tecnicoV[]',$tecnicos ?? [''=>''], '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}'+'</td>'+


            '<td><a href="#" class="btn btn-danger removeTecnicoV"><i class="glyphicon glyphicon-remove"></i></a></td>'+
            '</tr>';
        $('#cuerpoTecnicoV').append(tr);
        $('.js-example-basic-single').select2();
    };
    $('body').on('click', '.removeTecnicoV', function(e){
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

            '<td>'+'{{ Form::select('tipo[]',['Principal'=>'Principal','Linea 1'=>'Linea 1','Linea 2'=>'Linea 2','Cuarto'=>'Cuarto','VAR'=>'VAR'], '',['class' => 'form-control']) }}'+'</td>'+
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

    $('.addRowTorneo').on('click',function(e){
        e.preventDefault();
        addRowTorneo();
    });
    function addRowTorneo()
    {
        var tr='<tr>'+
            '<td></td><td>'+'{{ Form::select('torneoAnterior[]',$torneosAnteriores ?? [''=>''], '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}'+'</td>'+


            '<td><a href="#" class="btn btn-danger removeTorneo"><i class="glyphicon glyphicon-remove"></i></a></td>'+
            '</tr>';
        $('#cuerpoTorneo').append(tr);
        $('.js-example-basic-single').select2();
    };
    $('body').on('click', '.removeTorneo', function(e){
        e.preventDefault();

        $(this).parent().parent().remove();


    });

    $('.addRowTorneoAcumulado').on('click',function(e){
        e.preventDefault();
        addRowTorneoAcumulado();
    });
    function addRowTorneoAcumulado()
    {
        var tr='<tr>'+
            '<td></td><td>'+'{{ Form::select('torneoAnteriorAcumulado[]',$torneosAnteriores ?? [''=>''], '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}'+'</td>'+


            '<td><a href="#" class="btn btn-danger removeTorneoAcumulado"><i class="glyphicon glyphicon-remove"></i></a></td>'+
            '</tr>';
        $('#cuerpoTorneoAcumulado').append(tr);
        $('.js-example-basic-single').select2();
    };
    $('body').on('click', '.removeTorneoAcumulado', function(e){
        e.preventDefault();

        $(this).parent().parent().remove();


    });


    $('.addRowGrupo').on('click',function(e){
        e.preventDefault();
        addRowGrupo();
    });
    function addRowGrupo()

    {
        var $cant =parseInt($('#cantGrupos').val());
        $cant=$cant+1;
        $('#cantGrupos').val($cant);
        var tr='<tr>'+
            '<td><input type="hidden" name="items[]" value="'+$cant+'"></td><td>'+'{{ Form::text('nombreGrupo[]', '',['class' => 'form-control', 'style' => 'width: 250px']) }}'+'</td>'+
            '<td>'+'{{ Form::number('equiposGrupo[]', '',['class' => 'form-control', 'style' => 'width: 60px']) }}'+'</td>'+
            '<td>'+'{{ Form::number('agrupacionGrupo[]', '1',['class' => 'form-control', 'style' => 'width: 50px']) }}'+'</td>'+
            '<td><input type="checkbox" name="posicionesGrupo[]" value="'+$cant+'"></td>'+
            '<td><input type="checkbox" name="promediosGrupo[]" value="'+$cant+'"></td>'+
            '<td><input type="checkbox" name="acumuladoGrupo[]" value="'+$cant+'"></td>'+
            '<td><input type="checkbox" name="penalesGrupo[]" value="'+$cant+'"></td>'+
            '<td><a href="#" class="btn btn-danger removeGrupo"><i class="glyphicon glyphicon-remove"></i></a></td>'+
            '</tr>';
        $('#cuerpoGrupo').append(tr);
        $('.js-example-basic-single').select2();
    };
    $('body').on('click', '.removeGrupo', function(e){
        e.preventDefault();

        $(this).parent().parent().remove();


    });

    $('.addRowPosicion').on('click',function(e){
        e.preventDefault();
        addRowPosicion();
    });
    function addRowPosicion()

    {
        console.log($('#posicionEquipo').val());
        var $cant =parseInt($('#posicionEquipo').val());
        $cant=$cant+1;

        $('#posicionEquipo').val($cant);
        var tr='<tr>'+
            '<td><input type="hidden" name="posicion[]" value="'+$cant+'"></td>'+
            '<td>'+'{{ Form::select('equipo[]',$equipos ?? [''=>''], '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}'+'</td>'+


            '<td><a href="#" class="btn btn-danger removePosicionEquipo"><i class="glyphicon glyphicon-remove"></i></a></td>'+
            '</tr>';
        $('#cuerpoPosicion').append(tr);
        $('.js-example-basic-single').select2();
    };
    $('body').on('click', '.removePosicionEquipo', function(e){
        e.preventDefault();

        $(this).parent().parent().remove();


    });



</script>
