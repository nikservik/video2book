@extends('layouts.app')

@section('title', 'Dashboard | '.config('app.name', 'Video2Book'))
@section('heading', 'Dashboard')

@section('content')
    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-800">
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Server-first интерфейс готов. Следующий шаг: перенести в Livewire основные сценарии работы с проектами,
            уроками и пайплайнами.
        </p>
    </div>
@endsection
