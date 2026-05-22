<?php

return [
    'facsimile_max_long_edge'      => (int) env('FACSIMILE_MAX_LONG_EDGE', 2400),
    'facsimile_main_quality'       => (int) env('FACSIMILE_MAIN_JPEG_QUALITY', 85),
    'facsimile_thumb_width'        => (int) env('FACSIMILE_THUMB_WIDTH', 200),
    'facsimile_thumb_quality'      => (int) env('FACSIMILE_THUMB_JPEG_QUALITY', 80),
    'facsimile_memory_limit'       => env('FACSIMILE_MEMORY_LIMIT', '512M'),
    'version_editor_lazy_load'     => (bool) env('VARIANCE_VERSION_EDITOR_LAZY_LOAD', false),
];
