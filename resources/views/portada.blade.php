@extends('layouts.appPublic')

@section('content')
    <script type="text/javascript">
        window.location = "{{ url('/verFechas') }}";//here double curly bracket
    </script>
@endsection
