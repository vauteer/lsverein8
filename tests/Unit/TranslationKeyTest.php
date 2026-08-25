<?php

/**
 * Every string the frontend wraps in $t()/trans()/wTrans() has to exist in
 * lang/de.json, otherwise the UI silently falls back to the English source
 * string. Untranslated starter-kit pages are not flagged: they contain no
 * translation calls at all and so contribute no keys.
 *
 * Plain filesystem calls, no app boot: unit tests do not get the TestCase.
 */
test('every translation key used in the frontend is translated into German', function () {
    $root = dirname(__DIR__, 2);

    /** @var array<string, string> $translations */
    $translations = json_decode(
        (string) file_get_contents($root.'/lang/de.json'),
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/resources/js', FilesystemIterator::SKIP_DOTS)
    );

    $missing = [];

    foreach ($files as $file) {
        if (! in_array($file->getExtension(), ['vue', 'ts'], true)) {
            continue;
        }

        preg_match_all(
            // (?<![\w$]) rather than \b: $ is not a word character, so \b
            // never matches in front of the template helper's $t.
            '/(?<![\w$])(?:\$t|w?[tT]rans)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/',
            (string) file_get_contents($file->getPathname()),
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $key = stripcslashes(($match[2] ?? '') === '' ? $match[1] : $match[2]);

            if (! array_key_exists($key, $translations)) {
                $missing[$key][] = str_replace($root.'/', '', $file->getPathname());
            }
        }
    }

    expect($missing)->toBe([]);
});
