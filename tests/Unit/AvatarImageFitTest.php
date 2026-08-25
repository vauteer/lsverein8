<?php

/**
 * A profile photo is rendered into a fixed square box. Without an explicit
 * object-fit the browser default is "fill", which stretches anything that is
 * not already square — the photo looks squashed rather than cropped.
 *
 * AvatarImage lives in resources/js/components/ui, which is shadcn-generated
 * and prettier-ignored, so regenerating the component would silently restore
 * the stretching version and nothing else would fail.
 *
 * Plain filesystem calls, no app boot: unit tests do not get the TestCase.
 */
test('the avatar image crops instead of stretching', function () {
    $root = dirname(__DIR__, 2);

    $component = (string) file_get_contents(
        $root.'/resources/js/components/ui/avatar/AvatarImage.vue'
    );

    expect($component)->toContain('object-cover')
        // Merged through cn(), not hardcoded: object-cover and object-contain
        // have the same specificity, so a caller passing object-contain (club
        // logos) is only honoured if tailwind-merge drops the default.
        ->and($component)->toContain('cn(');
});

test('club logos are contained rather than cropped', function () {
    $root = dirname(__DIR__, 2);

    // Cropping a logo to a square can cut the club name out of the wordmark,
    // so these three call sites deliberately override the photo default.
    foreach ([
        '/resources/js/components/ClubSwitcher.vue',
        '/resources/js/pages/clubs/Index.vue',
    ] as $file) {
        $contents = (string) file_get_contents($root.$file);

        $images = substr_count($contents, '<AvatarImage');
        $contained = substr_count($contents, 'object-contain');

        expect($contained)->toBe($images, "{$file} leaves a logo cropped");
    }
});
