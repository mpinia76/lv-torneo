@extends('layouts.app')

@section('pageTitle', 'Jueces')

@section('content')
    <div class="container">

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
    {{ Form::open(['action' => ['PartidoArbitroController@update', (isset($_GET['partidoId']))?$_GET['partidoId']:''], 'method' => 'put']) }}
    <!-- Include the CRSF token -->
    {{Form::token()}}
        {{Form::hidden('partido_id', (isset($_GET['partidoId']))?$_GET['partidoId']:'' )}}

    <!-- build our form inputs -->
        <div class="row">

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

        </div>
        <div class="row">

            <div class="form-group col-md-12">
                <h1 class="display-6">Jueces</h1>
                <a class="btn btn-success m-1" href="{{route('arbitros.create',  array('partidoId' => $partido->id))}}">Nuevo</a>
                <table class="table" style="width: 50%">
                    <thead>
                    <th></th>
                    <th>Arbitro</th>

                    <th>Tipo</th>
                    <th><a href="#" class="addRowArbitro"><i class="glyphicon glyphicon-plus"></i></a></th>

                    </thead>

                    <tbody id="cuerpoarbitro">

                    @foreach($partidoarbitros ?? '' as $arbitro)

                        <tr>

                            <td>@if($arbitro->arbitro->foto)
                                    <img id="original" class="imgCircle" src="{{ url('images/'.$arbitro->arbitro->foto) }}" >
                                @else
                                    <img id="original" class="imgCircle" src="{{ url('images/sin_foto_arbitro.png') }}" >
                                @endif
                                {{Form::hidden('partidoarbitro_id[]',$arbitro->id)}}
                            </td>
                            <td>{{ Form::select('arbitro[]',$arbitros, $arbitro->arbitro->id,['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>

                            <td>{{ Form::select('tipo[]',['Principal'=>'Principal','Linea 1'=>'Linea 1','Linea 2'=>'Linea 2','Cuarto'=>'Cuarto','VAR'=>'VAR'], $arbitro->tipo,['class' => 'form-control']) }}</td>
                            <td><a href="#" class="btn btn-danger removearbitro"><i class="glyphicon glyphicon-remove"></i></a></td>
                        </tr>
                    @endforeach
                    </tbody>




                </table>
            </div>

        </div>

    {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
        <a href="{{ route('fechas.show',$partido->fecha->id) }}" class="btn btn-success m-1">Volver</a>
    {{ Form::close() }}
    </div>
@endsection
