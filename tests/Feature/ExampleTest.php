<?php

it('redirects unauthenticated users to login', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});

it('renders the login screen successfully', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});
