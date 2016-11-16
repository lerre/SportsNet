<?php

$app->group('', function () {
    $this->map(['GET', 'POST'], '/evenement/create', 'EvenementController:create')->setName('evenement.create');
    $this->map(['GET', 'POST'], '/evenement/{id:[0-9]+}/edit', 'EvenementController:edit')->setName('evenement.edit');
})->add(new App\Middleware\AuthMiddleware($container));
