@extends('statamic::layout')
@section('title', 'Content Sync')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="flex items-center">
            <svg class="h-6 w-6 text-gray-700 mr-2" viewBox="0 0 24 24" fill="currentColor"><path d="M5 4h14a1 1 0 0 1 1 1v8.382a1 1 0 0 1-.293.707l-6.618 6.618a1 1 0 0 1-.707.293H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1zm8 15.586L18.586 14H13v5.586z"/></svg>
            Content Sync
        </h1>
    </div>

    <div class="card mb-4 p-4 text-gray-700">
        <p class="mb-1"><strong>Export &amp; Import</strong> Collections, Navigation, Taxonomies, Globals, and Asset metadata.</p>
        <p class="text-gray-600">Preview changes like Git: <span class="text-green-700 font-semibold">+ added</span>,
            <span class="text-amber-700 font-semibold">? changed</span>,
            <span class="text-red-700 font-semibold">- removed</span>.
            Choose <em>Accept current</em>, <em>Accept incoming</em>, or <em>Accept both</em> and review the computed <em>Final merge</em>.
        </p>
    </div>

    <content-sync-utility></content-sync-utility>
@endsection
