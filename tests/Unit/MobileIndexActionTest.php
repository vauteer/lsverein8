<?php

/**
 * Every index page keeps its header actions reachable on a phone. Until
 * 2026-08-28 the create button was simply dropped below the md breakpoint
 * ("hidden md:inline-flex", or a "hidden … md:flex" wrapper around the whole
 * action bar), so a mobile user could see a list but never add to it.
 *
 * The button now stays and only its label collapses, leaving the plus icon,
 * which is why the label sits in a max-md:hidden span and the control carries
 * an aria-label of its own.
 *
 * Plain filesystem calls, no app boot: unit tests do not get the TestCase.
 */
$indexPages = glob(dirname(__DIR__, 2).'/resources/js/pages/*/Index.vue') ?: [];

test('no index page hides a header action below the md breakpoint', function () use ($indexPages) {
    expect($indexPages)->not->toBeEmpty();

    foreach ($indexPages as $page) {
        $contents = (string) file_get_contents($page);
        expect($contents)->not->toContain('hidden md:inline-flex');
        expect($contents)->not->toMatch('/class="hidden[^"]*\bmd:flex\b/');
    }
});

test('a create button keeps a label that collapses to the plus icon', function () use ($indexPages) {
    // Backups creates through a form whose label doubles as progress feedback
    // ("Creating …"), so it keeps its text at every width.
    $pages = array_filter(
        $indexPages,
        fn (string $page): bool => basename(dirname($page)) !== 'backups',
    );

    foreach ($pages as $page) {
        $contents = (string) file_get_contents($page);
        expect($contents)
            ->toMatch('/:aria-label="\$t\(\'New /')
            ->toContain('<span class="max-md:hidden">');
    }
});
