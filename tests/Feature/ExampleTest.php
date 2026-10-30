<?php

test('example', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
