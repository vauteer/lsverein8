<?php

test('the branded icon files are published', function (string $file) {
    expect(public_path($file))->toBeFile();
})->with(['favicon.ico', 'favicon.svg', 'apple-touch-icon.png']);

test('the favicon uses the app icon glyph on the brand colour', function () {
    $svg = file_get_contents(public_path('favicon.svg'));

    expect($svg)
        ->toContain('#312e81')
        ->toContain('circle cx="17.695" cy="17.695" r="3"');
});

test('the layout links every icon variant', function () {
    $blade = file_get_contents(resource_path('views/app.blade.php'));

    expect($blade)
        ->toContain('<link rel="icon" href="/favicon.ico" sizes="any">')
        ->toContain('<link rel="icon" href="/favicon.svg" type="image/svg+xml">')
        ->toContain('<link rel="apple-touch-icon" href="/apple-touch-icon.png">');
});
