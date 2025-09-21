@props(['data', 'columns'])

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
