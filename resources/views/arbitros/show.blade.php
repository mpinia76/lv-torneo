@extends('layouts.app')

@section('pageTitle', 'Ver arbitro')

@section('content')
    <div class="container">
    <h1 class="display-6">Ver arbitro</h1>

        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Nombre</dt>
                <dd>{{$arbitro->nombre}}</dd>
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Apellido</dt>
                <dd>{{$arbitro->apellido}}</dd>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Documento</dt>
                <dd>{{$arbitro->tipoDocumento}} {{$arbitro->documento}}</dd>
            </div>

        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>E-mail</dt>
                <dd>{{$arbitro->email}}</dd>

            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Teléfono</dt>
                <dd>{{$arbitro->telefono}}</dd>
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Ciudad Nacimiento</dt>
                <dd>{{$arbitro->ciudad}}</dd>

            </div>
        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <dt>Edad</dt>
                <dd>{{($arbitro->nacimiento)?$arbitro->getAgeAttribute():''}}</dd>

            </div>

        </div>
        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">

                    @if($arbitro->foto)
                        <img id="original" src="{{ url('images/'.$arbitro->foto) }}" height="200">
                    @endif


                </div>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-6">
                <dt>Observaciones</dt>
                <dd>{{$arbitro->observaciones}}</dd>

            </div>

        </div>






    <div class="d-flex">
        <a href="{{route('arbitros.edit', $arbitro->id)}}" class="btn btn-primary m-1">Editar</a>

        <form action="{{ route('arbitros.destroy', $arbitro->id) }}" method="POST" onsubmit="return  ConfirmDelete()">
            <input type="hidden" name="_method" value="DELETE">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
            <button class="btn btn-danger m-1">Eliminar</button>
        </form>
        <a href="{{ route('arbitros.index') }}" class="btn btn-success m-1">Volver</a>
    </div>
    </div>
    <script>

        function ConfirmDelete()
        {
            var x = confirm("Está seguro?");
            if (x)
                return true;
            else
                return false;
        }

    </script>
@endsection
