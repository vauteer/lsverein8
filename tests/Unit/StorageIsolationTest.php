<?php

/**
 * The public disk must be faked for every feature test, not per test file.
 *
 * ProfileController and ClubController sweep orphaned files on every save, and
 * the test database is empty, so an unfaked sweep treats every real profile
 * image and club logo as an orphan and deletes it. On 2026-08-25 a test run
 * did exactly that to both real club logos, because the fake lived in
 * individual test files and a newly added file did not have it.
 *
 * Plain filesystem check, no app boot: unit tests do not get the TestCase.
 */
test('the public disk is faked for the whole feature suite', function () {
    $pest = (string) file_get_contents(dirname(__DIR__).'/Pest.php');

    expect($pest)->toMatch("/beforeEach\(fn \(\) => Storage::fake\('public'\)\)/")
        ->and($pest)->toContain("->in('Feature')");
});
