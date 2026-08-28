@extends('layouts.app')

@section('pageTitle', $titulo)

@section('content')

    {{-- Las pantallas del importador se arman como HTML en el controlador
         (ImportPartidosController / ImportDetallesController) y se inyectan acá,
         para que queden dentro del layout con el menú de administración.

         TODO el CSS va prefijado con .import-tm: sin eso, reglas como
         body/h1/a/table/th,td pisaban los estilos del sitio entero. --}}

    <div class="import-tm">
        {!! $cuerpo !!}
    </div>

    <style>
        .import-tm{font:14px/1.5 -apple-system,Segoe UI,Roboto,sans-serif;color:#1a1f1c}
        .import-tm h1{font-size:22px;margin:0 0 4px}
        .import-tm h2{font-size:16px;margin:28px 0 8px}
        .import-tm .sub{color:#6b7a73;margin:0 0 8px;font-size:12.5px}
        .import-tm .acciones{margin:12px 0}
        .import-tm .acciones a{color:#15714e;margin-right:2px}
        .import-tm a{color:#15714e}
        .import-tm a.boton,.import-tm .acciones a.boton{display:inline-block;background:#15714e;color:#fff;padding:5px 12px;text-decoration:none;font-weight:600}
        .import-tm a.boton:hover{background:#0f5a3d;color:#fff}
        .import-tm a.boton-sec{display:inline-block;margin-left:8px;padding:4px 10px;border:1px solid #c7cec7;background:#eef1ec;color:#15714e;text-decoration:none;font-size:12px}
        .import-tm a.boton-sec:hover{background:#e2e8e1}
        .import-tm .diag{background:#fff;border:1px solid #dde2dd;padding:14px 16px;font-size:13px}
        .import-tm .diag code{background:#eef1ec;padding:1px 5px;font-size:12px}
        .import-tm pre{font-size:11px;max-height:420px;overflow:auto;background:#f0f3ef;padding:10px}
        .import-tm .cards{display:flex;flex-wrap:wrap;gap:1px;background:#dde2dd;border:1px solid #dde2dd;margin:14px 0}
        .import-tm .card{background:#fff;padding:10px 16px;min-width:110px}
        .import-tm .card b{display:block;font-size:20px}
        .import-tm .card span{font-size:11px;color:#6b7a73;text-transform:uppercase;letter-spacing:.06em}
        .import-tm .card.ok b{color:#15714e}
        .import-tm .card.err b{color:#9c3529}
        .import-tm .card.warn b{color:#8a5d00}
        .import-tm .card.gris b{color:#9aa69f}
        .import-tm .ok-box{background:#ddede4;border:1px solid #15714e;padding:10px 14px;margin:10px 0}
        .import-tm .err-box{background:#f6e2de;border:1px solid #9c3529;padding:10px 14px;margin:10px 0}
        .import-tm .err{color:#9c3529}
        .import-tm .ok{color:#15714e}
        .import-tm .warn{color:#8a5d00}
        .import-tm tr.warn td{background:#fdf6e6}
        .import-tm tr.gris{color:#9aa69f}
        .import-tm .gris{color:#9aa69f}
        .import-tm .scroll{overflow:auto;border:1px solid #dde2dd;background:#fff;max-height:70vh}
        .import-tm table{border-collapse:collapse;width:100%;font-size:12.5px;margin:0}
        .import-tm th,.import-tm td{padding:6px 10px;border-bottom:1px solid #eceee9;text-align:left;white-space:nowrap}
        .import-tm thead th{position:sticky;top:0;background:#eef1ec;font-size:11px;text-transform:uppercase;letter-spacing:.05em;z-index:1}
        .import-tm td.num{font-variant-numeric:tabular-nums}
        .import-tm .id{color:#9aa69f;font-size:11px}
        .import-tm input,.import-tm button,.import-tm select{font:13px inherit;padding:3px 6px;border:1px solid #c7cec7;background:#fff}
        .import-tm button{cursor:pointer;background:#eef1ec}
        .import-tm details summary{cursor:pointer;color:#15714e;margin-top:6px}
        .import-tm form{display:inline-block;margin:0}

        /* El desplegable de select2 se monta al final del body: sólo tocamos
           el control que vive adentro de .import-tm, para no cambiarle el
           aspecto a los select2 del resto del sitio. */
        .import-tm .select2-container{vertical-align:middle}
        .import-tm .select2-container--default .select2-selection--single{border-color:#c7cec7;border-radius:0;height:28px}
        .import-tm .select2-container--default .select2-selection--single .select2-selection__rendered{line-height:26px;font-size:13px}
        .import-tm .select2-container--default .select2-selection--single .select2-selection__arrow{height:26px}
    </style>
@endsection

@section('bottom')
    {{-- jQuery y select2 ya vienen del layout (partials/footer): no se
         re-incluyen, o select2 se inicializaba dos veces. --}}
    <script>
        $(function () {
            $('.import-tm .s2').each(function () {
                $(this).select2({
                    width: '260px',
                    placeholder: $(this).data('placeholder') || '',
                    allowClear: true
                });
            });
        });
    </script>
@endsection
