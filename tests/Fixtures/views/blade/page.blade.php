@extends('blade/layout')
@section('title', 'Dashboard')
@section('content')
@if (count($items) > 0)
@foreach ($items as $item)
@include('blade/item', ['item' => $item])
@endforeach
@else
<p>Empty</p>
@endif
{!! $trusted !!}
@endsection
