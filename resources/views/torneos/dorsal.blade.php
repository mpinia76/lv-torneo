@extends('layouts.app')

@section('pageTitle', 'Modificar dorsales')

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
            {{ Form::open(['action' => ['TorneoController@guardarDorsal'], 'method' => 'put']) }}
            <!-- Include the CRSF token -->
            {{Form::token()}}
            <!-- build our form inputs -->
            <input type="hidden" id="torneoId" name="torneoId" value="{{$torneo->id}}">
            <select class="form-control js-example-basic-single" id="equipo1" name="equipo1" onchange="this.form.submit()" style="width: 150px">

                @foreach($equipos as $id => $equipo)

                    <option value="{{$id}}" @if($id==$e1->id)
                        selected

                        @endif />{{$equipo}}</option>
                @endforeach

            </select>




<hr>
            @if($e1->escudo)<img id="original" src="{{ url('images/'.$e1->escudo) }}" height="100">
            @endif


        <div class="row">

            <div class="form-group col-md-12">
                <h1 class="display-6">

                    Jugador</h1>
                <table class="table" style="width: 60%">
                    <thead>

                    <th>Jugador</th>
                    <th>Dorsal</th>




                    </thead>

                    <tbody >


                        <tr>


                            <td>{{ Form::select('jugador_id',$jugadorsL, '',['class' => 'form-control js-example-basic-single', 'style' => 'width: 300px']) }}</td>
                            <td>{{Form::number('dorsal', '', ['class' => 'form-control', 'style' => 'width:70px;'])}}</td>


                        </tr>

                    </tbody>




                </table>
            </div>
        </div>


            {{Form::submit('Guardar', ['class' => 'btn btn-primary'])}}
            <a href="{{ route('torneos.ver',array('torneoId' => $torneo->id))}}" class="btn btn-success m-1">Volver</a>
            {{ Form::close() }}

    </div>


@endsection
