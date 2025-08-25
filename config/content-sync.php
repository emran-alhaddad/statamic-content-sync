<?php

return [
    'disk'   => env('CONTENT_SYNC_DISK', 'local'),
    'folder' => env('CONTENT_SYNC_FOLDER', 'sync'),
    'chunk'  => env('CONTENT_SYNC_CHUNK', 500),
];
