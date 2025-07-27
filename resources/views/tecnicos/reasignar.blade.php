@extends('layouts.app')

@section('pageTitle', 'Reasignar tecnico')

@section('content')
    <div class="container">
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
        {{ Form::open(['action' => ['TecnicoController@guardarReasignar'], 'method' => 'put']) }}
        <!-- Include the CRSF token -->
        {{Form::token()}}
            <!-- build our form inputs -->
            <input type="hidden" id="tecnicoId" name="tecnicoId" value="{{$tecnico->id}}">
    <h1 class="display-6">Reasignar tecnico</h1>

        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Nombre</dt>
                <dd>{{$tecnico->persona->nombre}}</dd>
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <dt>Apellido</dt>
                <dd>{{$tecnico->persona->apellido}}</dd>
            </div>
            <div class="form-group col-xs-12 col-sm-6 col-md-3">
                <dt>Edad</dt>
                {!! ($tecnico->persona->fallecimiento)?'<img id="original" src="'.url('images/death.png').'">':'' !!}
                <dd>{{($tecnico->persona->nacimiento)?$tecnico->persona->getAgeAttribute():''}}</dd>

            </div>

        </div>

        <div class="row">

            <div class="form-group col-xs-12 col-sm-6 col-md-4">
                <div class="form-group">

                    @if($tecnico->persona->foto)
                        <img id="original" src="{{ url('images/'.$tecnico->persona->foto) }}" height="200">
                    @endif


                </div>
            </div>


        </div>
            <table class="table" style="width: 60%">
                <thead>
                <th></th>
                <th>Reasignar</th>


                </thead>

                <tbody id="cuerpoReasignar">


                    <tr>

                        <td>

                        </td>
                        <td>{{ Form::select('reasignarId',[],'' ,['id'=>'reasignarId','class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>

                    </tr>

                </tbody>




            </table>




            {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
            <a href="{{ route('jugadores.verificarPersonas')}}" class="btn btn-success m-1">Volver</a>
            {{ Form::close() }}
    </div>

@endsection
