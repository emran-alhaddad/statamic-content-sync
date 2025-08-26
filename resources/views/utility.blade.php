{{-- resources/views/utility.blade.php --}}
@extends('statamic::layout')
@section('title', 'Content Sync')

@section('content')
    {{-- Provide base URL and CSRF to the JS runtime --}}
    <script>
        window.__CONTENT_SYNC_BASE__ = "{{ cp_url('content-sync') }}"; // => /cp/content-sync
        window.__CONTENT_SYNC_CSRF__ = "{{ csrf_token() }}";
    </script>

    <div class="card mb-4 p-4 text-gray-700">
        <p class="mb-1"><strong>Export &amp; Import</strong> Collections, Navigation, Taxonomies, Globals, and Asset metadata.</p>
        <p class="text-gray-600">Preview changes like Git (<span class="text-green-700 font-semibold">+</span> added,
            <span class="text-amber-700 font-semibold">?</span> changed,
            <span class="text-red-700 font-semibold">-</span> removed) and choose what to apply.</p>
    </div>

    <content-sync-utility></content-sync-utility>
@endsection
