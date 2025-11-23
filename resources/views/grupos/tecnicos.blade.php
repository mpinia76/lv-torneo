@extends('layouts.appPublic')

@section('pageTitle', 'Técnicos')

@section('content')
    <div class="container">
        <div class="card shadow-sm border-0">
            <div class="card-body">
        <h1 class="h3 mb-4 text-center text-primary">👨‍💼 Técnicos</h1>


                {{-- Barra de búsqueda --}}
                <form class="d-flex justify-content-center mb-4">
                    <input type="hidden" name="torneoId" value="{{ $torneo_id }}">
                    <input type="search" name="buscarpor" class="form-control me-2" placeholder="Buscar técnico"
                           value="{{ request()->get('buscarpor', session('nombre_filtro_jugador')) }}" style="width: 250px;">
                    <button class="btn btn-success" type="submit">Buscar</button>
                </form>


        @php
            $columns = [
                'puntaje' => 'Punt.',
                'Jugados' => 'J',
                'Ganados' => 'G',
                'Empatados' => 'E',
                'Perdidos' => 'P',
                'golesl' => 'GF',
                'golesv' => 'GC',
                'diferencia' => 'Dif.',
                'prom' => '%',
            ];
        @endphp

        <table class="table table-striped table-hover align-middle" style="font-size: 14px;">
            <thead class="table-dark">
            <tr>
                <th>#</th>
                <th>Técnico</th>
                <th>Equipos</th>
                @foreach($columns as $key => $label)
                    @php
                        $colOrder = ($order == $key) ? ($tipoOrder == 'ASC' ? 'DESC' : 'ASC') : 'ASC';
                    @endphp
                    <th>
                        <a href="{{ route('grupos.tecnicos', ['torneoId' => $torneo_id, 'order' => $key, 'tipoOrder' => $colOrder]) }}" class="text-decoration-none text-white">
                            {{ $label }}
                            @if($order == $key)
                                <i class="bi {{ $tipoOrder=='ASC' ? 'bi-arrow-up' : 'bi-arrow-down' }} text-white"></i>
                            @endif
                        </a>
                    </th>
                @endforeach
            </tr>
            </thead>
            <tbody>
            @foreach($goleadores as $tecnico)
                <tr>
                    <td>{{ $i++ }}</td>
                    <td class="d-flex align-items-center gap-2">
                        <a href="{{ route('tecnicos.ver', ['tecnicoId' => $tecnico->tecnico_id]) }}">
                            <img class="imgCircle" src="{{ url('images/' . ($tecnico->fotoTecnico ?? 'sin_foto_tecnico.png')) }}" width="35" height="35" alt="Foto">
                        </a>
                        {{ $tecnico->tecnico }}
                        <img src="{{ url('images/' . removeAccents($tecnico->nacionalidadTecnico) . '.gif') }}" alt="{{ $tecnico->nacionalidadTecnico }}">
                    </td>
                    <td>
                        @if($tecnico->escudo)
                            @foreach(explode(',', $tecnico->escudo) as $escudo)
                                @if($escudo != '')
                                    @php $escudoArr = explode('_', $escudo); @endphp
                                    <a href="{{ route('equipos.ver', ['equipoId' => $escudoArr[1]]) }}">
                                        <img src="{{ url('images/'.$escudoArr[0]) }}" height="25" title="{{$escudoArr[4]}}" alt="{{$escudoArr[4]}}">
                                    </a>
                                    Puntaje {{$escudoArr[2] ?? ''}} - Porcentaje {{$escudoArr[3] ?? ''}}

                                    <br>
                                @endif
                            @endforeach
                        @endif
                    </td>
                    <td>{{ $tecnico->puntaje }}</td>
                    <td><a href="{{ route('tecnicos.jugados', ['tecnicoId' => $tecnico->tecnico_id,'torneoId'=>$torneo_id]) }}">{{ $tecnico->jugados }}</a></td>
                    <td><a href="{{ route('tecnicos.jugados', ['tecnicoId' => $tecnico->tecnico_id,'torneoId'=>$torneo_id,'tipo'=>'Ganados']) }}">{{ $tecnico->ganados }}</a></td>
                    <td><a href="{{ route('tecnicos.jugados', ['tecnicoId' => $tecnico->tecnico_id,'torneoId'=>$torneo_id,'tipo'=>'Empatados']) }}">{{ $tecnico->empatados }}</a></td>
                    <td><a href="{{ route('tecnicos.jugados', ['tecnicoId' => $tecnico->tecnico_id,'torneoId'=>$torneo_id,'tipo'=>'Perdidos']) }}">{{ $tecnico->perdidos }}</a></td>
                    <td>{{ $tecnico->golesl }}</td>
                    <td>{{ $tecnico->golesv }}</td>
                    <td>{{ $tecnico->diferencia }}</td>
                    <td>{{ $tecnico->porcentaje }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>

        {{-- Paginación y total --}}
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div>{{ $goleadores->links() }}</div>
            <div><strong>Total: {{ $goleadores->total() }}</strong></div>
        </div>

        <div class="d-flex mt-3">
            <a href="{{ url()->previous() }}" class="btn btn-success m-1">Volver</a>
        </div>
            </div>
        </div>
    </div>
@endsection
