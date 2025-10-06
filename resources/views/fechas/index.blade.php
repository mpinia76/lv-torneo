@extends('layouts.app')

@section('pageTitle', 'Listado de fechas')

@section('content')
    <div class="container">
    <h1 class="display-6">Fechas del grupo {{$grupo->nombre}} de {{$grupo->torneo->nombre}} - {{$grupo->torneo->year}}</h1>

    <hr/>
        @if (\Session::has('error'))
            <div class="alert alert-danger">
                <ul>
                    <li>{!! \Session::get('error') !!}</li>
                </ul>
            </div>
        @endif
        @if (\Session::has('success'))
            <div class="alert alert-success">
                <ul>
                    <li>{!! \Session::get('success') !!}</li>
                </ul>
            </div>
        @endif
        <a class="btn btn-success m-1" href="{{route('fechas.create',  array('grupoId' => (isset($_GET['grupoId']))?$_GET['grupoId']:'' ))}}">Nuevo</a>
        <a class="btn btn-info m-1" href="{{route('fechas.import',  array('grupoId' => (isset($_GET['grupoId']))?$_GET['grupoId']:'' ))}}">Importar datos</a>
        <a href="{{route('fechas.importgolesfecha',  array('grupoId' => (isset($_GET['grupoId']))?$_GET['grupoId']:'' ))}}" class="btn btn-info m-1">Importar goles</a>

        <!--<a href="{{route('fechas.controlarbitrosfecha',  array('grupoId' => (isset($_GET['grupoId']))?$_GET['grupoId']:'' ))}}" class="btn btn-info m-1">Controlar arbitros</a>-->
        <nav class="navbar navbar-light float-right">
            <form class="form-inline">
                <input type="hidden" name="grupoId" value="{{ (isset($_GET['grupoId']))?$_GET['grupoId']:'' }}">
                <input  value="{{ (isset($_GET['buscarpor']))?$_GET['buscarpor']:session('nombre_filtro_fecha')}}" name="buscarpor" class="form-control mr-sm-2" type="search" placeholder="Buscar" aria-label="Search">

                <button class="btn btn-success m-1" type="submit">Buscar</button>
            </form>
        </nav>

    <table class="table">
        <thead>
        <th>Numero</th>
        <th>Grupo</th>
        <th>Torneo</th>

        <th colspan="3"></th>
        </thead>

        @foreach($fechas as $fecha)

            <tr>
                <td>{{$fecha->numero}}</td>
                <td>{{$fecha->grupo->nombre}}</td>
                <td>{{$fecha->grupo->torneo->nombre}} - {{$fecha->grupo->torneo->year}}</td>

                <td>
                    <div class="d-flex">

                        <a href="{{route('fechas.edit', $fecha->id)}}" class="btn btn-primary m-1">Editar</a>
                        <a href="{{route('fechas.show', $fecha->id)}}" class="btn btn-success m-1">Datos complementarios</a>
                        <!--<a href="{{route('fechas.importincidenciasfecha', array('fechaId' =>$fecha->id))}}" class="btn btn-info m-1">Importar incidencias</a>
                        <a href="{{route('fechas.importgolesfecha', array('fechaId' =>$fecha->id))}}" class="btn btn-info m-1">Importar goles</a>-->
                        <a href="{{ route('fechas.importpenalesfecha', ['fechaId' => $fecha->id]) }}"
                           class="btn btn-info m-1"
                           onclick="return confirmarImportacion({{ $fecha->penales_importados ? 'true' : 'false' }}, {{ $fecha->id }})">
                            Importar penales
                        </a>

                    </div>

                </td>
            </tr>
        @endforeach
    </table>
        <div class="d-flex">

            <a href="{{route('torneos.show',$grupo->torneo->id)}}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>
    <script>
        function confirmarImportacion(importada, fechaId) {
            if (importada) {
                return confirm('⚠️ Esta fecha ya tiene penales importados. ¿Deseas reprocesarla?');
            }
            return true;
        }
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
