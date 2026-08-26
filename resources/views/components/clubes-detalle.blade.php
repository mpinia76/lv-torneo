{{--
    Fila que se abre debajo de la del protagonista con el detalle por club.
    Va inmediatamente después de su <tr> y con el mismo id que <x-clubes-celda>.
--}}
@props(['clubes' => [], 'id' => null, 'cols' => 2])

@if(count($clubes))
    <tr class="t-lista-detalle" id="{{ $id }}" hidden>
        <td colspan="{{ $cols }}">
            <div class="t-lista-clubes">
                @foreach($clubes as $club)
                    @php $tit = titulosDesdeCadena($club['titulos'] ?? ''); @endphp
                    <a class="t-lista-club" href="{{ route('equipos.ver', ['equipoId' => $club['id']]) }}">
                        <x-escudo :src="$club['escudo']" :nombre="$club['nombre'] ?? ''"/>
                        <span class="t-lista-club-nom">{{ $club['nombre'] ?: 'Equipo' }}</span>
                        @if(!empty($club['dato']))
                            <span class="t-lista-club-dato">{{ $club['dato'] }}</span>
                        @endif
                        @if($tit)
                            <span class="t-chip t-chip-acento" title="{{ $tit['detalle'] }}">
                                <i class="bi bi-trophy-fill"></i>{{ $tit['total'] }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </td>
    </tr>
@endif
