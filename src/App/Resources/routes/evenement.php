<?php

$app->get('/evenement/{id_evenement:[0-9]+}', 'EvenementController:show')->setName('evenement.show');

$app->group('', function () {
    $this->map(['GET', 'POST'], '/evenement/create', 'EvenementController:create')->setName('evenement.create');
    $this->map(['GET', 'POST'], '/evenement/{id:[0-9]+}/edit', 'EvenementController:edit')->setName('evenement.edit');

    $this->post('/evenement/{id:[0-9]+}', 'EvenementController:getParticipants')->setName('evenement.participants');
    $this->get('/evenement/{id:[0-9]+}/cancel', 'EvenementController:cancel')->setName('evenement.cancel');
    $this->get('/evenement/{id:[0-9]+}/delete', 'EvenementController:delete')->setName('evenement.delete');
})->add(new App\Middleware\AuthMiddleware($container));
