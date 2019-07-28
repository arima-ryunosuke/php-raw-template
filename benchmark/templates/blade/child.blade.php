@extends('parent')

@section('title')child | @parent @endsection

@section('main')
@parent
This is child body.
this is {{ $value }}
{{ $ex->getCode() }}
{{ $array[3] }}
@foreach ($array as $key => $value)
    @if ($key == 2)
        {{ $value }}
    @endif
@endforeach
@endsection
