<?php

it('opens the application login from the root URL', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
