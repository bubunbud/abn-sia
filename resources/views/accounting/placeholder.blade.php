@extends('layouts.odoo')

@section('title', $title)

@section('content')
    <div class="bg-white rounded border border-odoo-border shadow-sm p-8 text-center">
        <h1 class="text-xl font-semibold text-gray-800 mb-2">{{ $title }}</h1>
        <p class="text-gray-500 text-sm">Modul ini akan dikembangkan pada fase berikutnya.</p>
    </div>
@endsection
