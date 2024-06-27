@extends('layouts.appPublic')

@section('content')
    <script type="text/javascript">
        window.location = "{{ url('/fixture') }}";//here double curly bracket
    </script>
@endsection
