<?php

/**
 * The flash toasts are shown top-center. The position is set once, as the
 * default of the shared Sonner wrapper, so both app layouts inherit it.
 *
 * That wrapper lives in resources/js/components/ui, which is shadcn-generated
 * and prettier-ignored: regenerating the component would silently restore
 * vue-sonner's own 'bottom-right' default, and nothing else would fail.
 *
 * Plain filesystem calls, no app boot: unit tests do not get the TestCase.
 */
test('the toaster is positioned top-center for every layout', function () {
    $root = dirname(__DIR__, 2);

    $wrapper = (string) file_get_contents(
        $root.'/resources/js/components/ui/sonner/Sonner.vue'
    );

    expect($wrapper)->toMatch("/position:\s*'top-center'/");

    // Neither layout may override it back, which would split the setting.
    foreach (['AppSidebarLayout', 'AppHeaderLayout'] as $layout) {
        $contents = (string) file_get_contents(
            $root."/resources/js/layouts/app/{$layout}.vue"
        );

        expect($contents)->toContain('<Toaster />');
    }
});
