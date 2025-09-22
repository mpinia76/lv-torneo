@php
    $i = 1;
@endphp

<table class="table table-striped table-hover align-middle">
    <thead class="table-dark">
    <tr>
        @foreach($columns as $title => $field)
            <th>{{ $title }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach($data as $row)
        <tr>
            @foreach($columns as $title => $field)
                <td>
                    @if($field === 'index')
                        {{ $i }}
                    @elseif($field === 'numero')
                        @if(is_numeric($row->numero))
                            Fecha {{ $row->numero }}
                        @else
                            {{ $row->numero }}
                        @endif
                    @elseif($field === 'nombre')

                        @if(!empty($row->escudo))
                            <img src="{{ url('images/'.$row->escudo) }}"
                                 alt="escudo {{ $row->nombre }}"
                                 width="24" height="24"
                                 class="me-2 img-fluid d-inline">
                        @endif
                        {{ $row->nombre }}
                            @if($row->pais)<img src="{{ url('images/'.removeAccents($row->pais).'.gif') }}" >@endif
                    @elseif($field === 'nombreTorneo')

                        @if(!empty($row->escudoTorneo))
                            <img src="{{ url('images/'.$row->escudoTorneo) }}"
                                 alt="escudoTorneo {{ $row->nombre }}"
                                 width="24" height="24"
                                 class="me-2 img-fluid d-inline">
                        @endif
                        {{ $row->nombreTorneo }}

                    @else
                        {{ $row->$field }}
                    @endif
                </td>
            @endforeach
        </tr>
        @php $i++; @endphp
    @endforeach
    </tbody>
</table>
