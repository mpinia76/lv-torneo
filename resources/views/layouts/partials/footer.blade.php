<!-- JavaScripts -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.2.3/jquery.min.js" integrity="sha384-I6F5OKECLVtK/BL+8iSLDEHowSAfUo76ZL9+kGAgTRdiByINKJaqTPH/QVNS1VDb" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>



<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.6-rc.0/js/select2.min.js"></script>
<script src="{{asset('ini.js')}}"></script>


@yield('bottom')

<script>
    function baseUrl(url) {
        return '{{url('')}}/' + url;
    }

    $(document).ready(function() {

        $('.js-example-basic-single').select2();
    });
    $('.addRow').on('click',function(e){
        e.preventDefault();
        addRow();
    });
    function addRow()
    {
        var tr='<tr>'+
            '<td></td><td>'+'{{ Form::select('jugador[]',$jugadors ?? [''=>''], '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}'+'</td>'+
            '<td>'+'{{Form::number('dorsal[]', '', ['class' => 'form-control', 'size' => '4'])}}'+'</td>'+

            '<td><a href="#" class="btn btn-danger remove"><i class="glyphicon glyphicon-remove"></i></a></td>'+
            '</tr>';
        $('#cuerpoJugador').append(tr);
        $('.js-example-basic-single').select2();
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

    $('.addRowTecnico').on('click',function(e){
        e.preventDefault();
        addRowTecnico();
    });
    function addRowTecnico()
    {
        var tr='<tr>'+
            '<td></td><td>'+'{{ Form::select('tecnico[]',$tecnicos ?? [''=>''], '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}'+'</td>'+


            '<td><a href="#" class="btn btn-danger removeTecnico"><i class="glyphicon glyphicon-remove"></i></a></td>'+
            '</tr>';
        $('#cuerpoTecnico').append(tr);
        $('.js-example-basic-single').select2();
    };
    $('body').on('click', '.removeTecnico', function(e){
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

            '<td>'+'{{ Form::select('tipo[]',['Principal'=>'Principal','Linea 1'=>'Linea 1','Linea 2'=>'Linea 2','Cuarto'=>'Cuarto','VAR'=>'VAR']) }}'+'</td>'+
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

</script>
