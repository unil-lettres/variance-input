<?php
// Setup test environment
define('UPLOAD_ROOT', __DIR__.'/test_uploads');
@mkdir(UPLOAD_ROOT, 0755, true);

// Simulate form upload
$_FILES = [
    'xml' => [
        'tmp_name' => 'comparison.xml',
        'error' => UPLOAD_ERR_OK
    ],
    'archive' => [
        'tmp_name' => 'comparison.zip',
        'error' => UPLOAD_ERR_OK
    ]
];

// Include dependencies
require_once 'upload_functions.php';
require 'index.php';  // Your original script

// Display results
echo "\n=== Processed Files ===\n";
system('tree '.UPLOAD_ROOT);

echo "\n=== Source XHTML ===\n";
readfile(UPLOAD_ROOT.'/john_doe/sample_work/v1--sample_work--v1-v2/v1-v2/source.xhtml');

echo "\n\n=== Target XHTML ===\n";
readfile(UPLOAD_ROOT.'/john_doe/sample_work/v1--sample_work--v1-v2/v1-v2/target.xhtml');