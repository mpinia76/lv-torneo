@extends('layouts.appPublic')

@section('pageTitle', 'Titulos')

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h1 class="h3 mb-4 text-center text-primary">🏆 Títulos Histórico</h1>

                <form class="form-inline mb-3 d-flex justify-content-between align-items-center" id="formulario">


                    <div class="d-flex align-items-center">


                        <div class="form-check" >
                            <input type="checkbox" class="form-check-input" id="argentinos" name="argentinos" @if ($argentinos == 1) checked @endif onchange="enviarForm()">
                            <label class="form-check-label" for="argentinos">Argentinos</label>
                        </div>

                    </div>




                </form>







        <table class="table table-striped table-hover align-middle" style="font-size: 14px;">
            <thead class="table-dark">
            <th>#</th>
            <th>Equipo</th>

            @php
                $columns = [

                    'titulos' => 'Títulos',
                    'internacionales' => 'Internacionales',
                    'ligas' => 'Ligas',
                    'copas' => 'Copas',

                ];
            @endphp
            @foreach($columns as $key => $label)
                <th>
                    <a href="{{ route('torneos.titulos', [
                            'order' => $key,
                            'tipoOrder' => ($order==$key && $tipoOrder=='ASC') ? 'DESC' : 'ASC',
                            'argentinos' => $argentinos
                        ]) }}" class="text-decoration-none text-white">
                        {{ $label }}
                        @if($order==$key)
                            <i class="bi {{ $tipoOrder=='ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }}"></i>
                        @endif
                    </a>
                </th>
            @endforeach

            </thead>
            <tbody>

            @foreach($posiciones as $equipo)
                <tr>
                    <td>{{$i++}}</td>
                    <td>
                        <a href="{{route('equipos.ver', array('equipoId' => $equipo->id))}}" >
                        @if($equipo->escudo)
                            <img id="original" src="{{ url('images/'.$equipo->escudo) }}" height="25">
                        @endif
                        </a>
                        {{$equipo->nombre}} <img id="original" src="{{ url('images/'.removeAccents($equipo->pais).'.gif') }}" alt="{{ $equipo->pais }}"></td>


                    <td>{{$equipo->titulos}}</td>
                    <td>{{$equipo->internacionales}}</td>
                    <td>{{$equipo->ligas}}</td>
                    <td>{{$equipo->copas}}</td>

                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="row">
            <div class="form-group col-xs-12 col-sm-6 col-md-9">
                {{ $posiciones->links() }}
            </div>

            <div class="form-group col-xs-12 col-sm-6 col-md-2">
                <strong>Total: {{ $posiciones->total() }}</strong>
            </div>
        </div>
        <div class="d-flex">

            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
    </div>
        </div>
    </div>

    <script>


        function enviarForm() {
            $('#tipoOrder').val('DESC');
            $('#formulario').submit();
        }
    </script>

@endsection
