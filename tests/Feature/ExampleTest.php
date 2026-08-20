<?php

test('the homepage redirects to protocols', function () {
    $response = $this->get('/');

    $response->assertRedirect('/protocols');
});