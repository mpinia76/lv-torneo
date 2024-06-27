@extends('layouts.app')

@section('pageTitle', 'Importar incidencias de un partido')

@section('content')
    <div class="container">
    <h1 class="display-6">Importar incidencias</h1>
        <div class="form-group col-md-12">
            <h1 class="display-6">Partido</h1>
            <table class="table">
                <thead>
                <th>Fecha</th>
                <th>Local</th>
                <th>GL</th>
                <th>GV</th>
                <th>Visitante</th>

                </thead>

                <tr>
                    <td>{{($partido->dia)?date('d/m/Y H:i', strtotime($partido->dia)):''}}</td>
                    <td>@if($partido->equipol)
                            @if($partido->equipol->escudo)<img id="original" src="{{ url('images/'.$partido->equipol->escudo) }}" height="20">
                            @endif
                            {{$partido->equipol->nombre}}
                        @endif
                    </td>
                    <td>{{$partido->golesl}}
                        @if($partido->penalesl)
                            ({{$partido->penalesl}})
                        @endif
                    </td>
                    <td>{{$partido->golesv}}
                        @if($partido->penalesv)
                            ({{$partido->penalesv}})
                        @endif
                    </td>
                    <td>@if($partido->equipov)
                            @if($partido->equipov->escudo)<img id="original" src="{{ url('images/'.$partido->equipov->escudo) }}" height="20">
                            @endif
                            {{$partido->equipov->nombre}}
                        @endif
                    </td>

                </tr>

            </table>
        </div>
    <hr/>

    <!-- if validation in the controller fails, show the errors -->
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Open the form with the store function route. -->
    {{ Form::open(['action' => 'FechaController@importarPartidoProcess']) }}

    <!-- Include the CRSF token -->
    {{Form::token()}}


    <!-- build our form inputs -->
        <div class="row">


            <div class="form-group col-xs-12 col-sm-6 col-md-6">
                {{Form::hidden('partido_id', (isset($_GET['partidoId']))?$_GET['partidoId']:'' )}}
                <?php //echo e(Form::label('url', 'URL')); ?>

                <?php //echo e(Form::text('url', '', ['class' => 'form-control'])); ?>

            </div>


        </div>
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-6">
                <?php echo e(Form::label('url2', 'URL')); ?>

                <?php echo e(Form::text('url2', '', ['class' => 'form-control'])); ?>


            </div>
        </div>

        <!-- build the submission button -->
        {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{route('fechas.show', $partido->fecha->id)}}" class="btn btn-success m-1">Volver</a>
        {{ Form::close() }}
    </div>


@endsection

