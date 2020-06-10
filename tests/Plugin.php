<?php

use Pest\Watch\Plugin;

it('has plugin', function () {
    assertTrue(class_exists(Plugin::class));
});
