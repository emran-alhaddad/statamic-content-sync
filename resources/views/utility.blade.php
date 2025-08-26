@extends('statamic::layout')
@section('title', 'Content Sync')

@section('content')
    <script>
        // Exact URLs so the JS never guesses.
        window.__CONTENT_SYNC_ENDPOINTS__ = {
            options: "{{ route('content-sync.options') }}",
            export : "{{ route('content-sync.export') }}",
            preview: "{{ route('content-sync.preview') }}",
            commit : "{{ route('content-sync.commit') }}",
            download: "{{ route('content-sync.download', ['file' => '__FILE__']) }}", // replace __FILE__ when needed
        };
        window.__CONTENT_SYNC_CSRF__ = "{{ csrf_token() }}";
    </script>

    <div class="card mb-4 p-4 text-gray-700">
        <p class="mb-1"><strong>Export &amp; Import</strong> Collections, Navigation, Taxonomies, Globals, and Asset metadata.</p>
        <p class="text-gray-600">
            Preview changes like Git (<span class="text-green-700 font-semibold">+</span> added,
            <span class="text-amber-700 font-semibold">?</span> changed,
            <span class="text-red-700 font-semibold">-</span> removed),
            decide per item (<em>current</em>, <em>incoming</em>, <em>both</em>), and review the <em>final merge</em>.
        </p>
    </div>

    <content-sync-utility></content-sync-utility>
@endsection
