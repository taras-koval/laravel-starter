@extends('_layouts.app')

@section('content')
    <div class="container my-auto">

        <div class="flex justify-center">
            <div class="text-lg font-bold tracking-wider border-r border-gray-400 pr-4">
                @yield('code')
            </div>

            <div class="ml-4 text-lg uppercase tracking-wider">
                @yield('message')
            </div>
        </div>

        <div class="text-center mt-12">
            <a href="{{ url()->previous() }}" class="underline-link-component">Go back</a>
        </div>
    </div>
@endsection
