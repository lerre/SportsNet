<?php

namespace App\Controller;

use Psr\Http\Message\RequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Respect\Validation\Validator as V;
use Illuminate\Database\QueryException;
use Upload\File;
use Upload\Storage\FileSystem;
use Upload\Validation\Mimetype;
use Upload\Validation\Size;
use App\Model\Epreuve;
use App\Model\Sportif;
use App\Model\Participe;
use App\Model\Evenement;

class EpreuveController extends Controller
{
    public function add(Request $request, Response $response, array $args)
    {
        $evenement = Evenement::find($args['event_id']);

        if (!$evenement) {
            throw $this->notFoundException($request, $response);
        }

        if ($evenement->user_id !== $this->user()->id) {
            $this->flash('danger', 'Cet événement ne vous appartient pas !');
            return $this->redirect($response, 'home');
        }

        if ($request->isPost()) {
            $this->validator->validate($request, [
                'nom' => V::notBlank(),
                'date_debut' => V::date('d/m/Y'),
                'heure_debut' => V::date('H:i'),
                'date_fin' => V::date('d/m/Y'),
                'heure_fin' => V::date('H:i'),
                'description' => V::notBlank(),
                'capacite' => V::notBlank()->numeric(),
                'prix' => V::notBlank()->numeric()
            ]);

            $storage = new FileSystem($this->getUploadDir($evenement->id));
            $file = new File('epreuve_pic_link', $storage);
            $file->setName('header');

            $file->addValidations(array(
                new Mimetype(['image/png', 'image/jpeg']),
                new Size('2M'),
            ));

            if (!$file->validate()) {
                $this->validator->addErrors('epreuve_pic_link', $file->getErrors());
            }

            if ($this->validator->isValid()) {
                $dated = $request->getParam('date_debut');
                $datef = $request->getParam('date_fin');
                $heured = $request->getParam('heure_debut');
                $heuref = $request->getParam('heure_fin');

                $dated = \DateTime::createFromFormat('d/m/Y H:i', $dated . ' ' . $heured);
                $datef = \DateTime::createFromFormat('d/m/Y H:i', $datef . ' ' . $heuref);

                $epreuve = new Epreuve([
                    'nom' => $request->getParam('nom'),
                    'capacite' => $request->getParam('capacite'),
                    'date_debut' => $dated,
                    'date_fin' => $datef,
                    'etat' => Epreuve::CREE,
                    'description' => $request->getParam('description'),
                    'prix' => $request->getParam('prix')
                ]);

                $epreuve->evenement()->associate($evenement);
                $epreuve->save();

                $file->upload();

                $this->flash('success', 'L\'épreuve a bien été créée !');
                return $this->redirect($response, 'home');
            }
        }

        return $this->view->render($response, 'Epreuve/add.twig');
    }

    public function edit(Request $request, Response $response, array $args)
    {
        $evenement = Evenement::find($args['event_id']);

        if (!$evenement) {
            throw $this->notFoundException($request, $response);
        }

        if ($evenement->user_id !== $this->user()->id) {
            $this->flash('danger', 'Cet événement ne vous appartient pas !');
            return $this->redirect($response, 'home');
        }

        $epreuve = Epreuve::find($args['trial_id']);

        if (!$epreuve) {
            throw $this->notFoundException($request, $response);
        }

        if ($request->isPost()) {
            $validation = $this->validator->validate($request, [
                'nom' => V::notBlank(),
                'date_debut' => V::date('d/m/Y'),
                'heure_debut' => V::date('H:i'),
                'date_fin' => V::date('d/m/Y'),
                'heure_fin' => V::date('H:i'),
                'description' => V::notBlank(),
                'capacite' => V::notBlank()->numeric(),
                'prix' => V::notBlank()->numeric()
            ]);

            $etat = $request->getParam('etat');

            $etats = [
                Evenement::CREE,
                Evenement::VALIDE,
                Evenement::OUVERT,
                Evenement::EN_COURS,
                Evenement::CLOS,
                Evenement::EXPIRE,
                Evenement::ANNULE
            ];

            if (!in_array($etat, $etats)) {
                $this->validator->addError('etat', 'État non valide.');
            }

            $file = new File('epreuve_pic_link', new FileSystem($this->getUploadDir($evenement->id), true));

            $file->addValidations([
                new Mimetype(['image/png', 'image/jpeg']),
                new Size('2M')
            ]);

            $fileUploaded = isset($_FILES['epreuve_pic_link']) && $_FILES['epreuve_pic_link']['error'] != UPLOAD_ERR_NO_FILE;

            if ($fileUploaded) {
                if (!$file->validate()) {
                    $this->validator->addErrors('epreuve_pic_link', $file->getErrors());
                }
            }

            if ($validation->isValid()) {
                $dated = $request->getParam('date_debut');
                $datef = $request->getParam('date_fin');
                $heured = $request->getParam('heure_debut');
                $heuref = $request->getParam('heure_fin');

                $dated = \DateTime::createFromFormat('d/m/Y H:i', $dated . ' ' . $heured);
                $datef = \DateTime::createFromFormat('d/m/Y H:i', $datef . ' ' . $heuref);

                $epreuve->fill([
                    'nom' => $request->getParam('nom'),
                    'capacite' => $request->getParam('capacite'),
                    'date_debut' => $dated,
                    'date_fin' => $datef,
                    'etat' => $request->getParam('etat'),
                    'description' => $request->getParam('description'),
                    'prix' => $request->getParam('prix'),
                ]);

                $epreuve->save();

                if ($fileUploaded) {
                    unlink($this->getPicturePath($evenement->id, $epreuve->id));
                    $file->setName($epreuve->id);
                    $file->upload();
                }

                $this->flash('success', 'L\'épreuve "' . $epreuve->nom . '" a bien été modifiée !');
                return $this->redirect($response, 'home');
            }
        }

        return $this->view->render($response, 'Epreuve/edit.twig', [
            'epreuve' => $epreuve
        ]);
    }

    public function show(Request $request, Response $response, array $args)
    {
        $evenement = Evenement::find($args['event_id']);

        if (!$evenement) {
            throw $this->notFoundException($request, $response);
        }

        if ($evenement->user_id !== $this->user()->id) {
            $this->flash('danger', 'Cet événement ne vous appartient pas !');
            return $this->redirect($response, 'home');
        }

        $epreuve = Epreuve::find($args['trial_id']);

        if (!$epreuve) {
            throw $this->notFoundException($request, $response);
        }

        return $this->view->render($response, 'Epreuve/show.twig', [
            'epreuve' => $epreuve,
            'evenement' => $evenement
        ]);
    }

    public function getUploadDir($eventId)
    {
        return $this->settings['events_upload'] . $eventId . '/epreuves';
    }

    public function getPicturePath($eventId, $trialId)
    {
        $path = $this->getUploadDir($eventId) . '/' . $trialId;
        return file_exists($path . '.jpg') ? $path . '.jpg' : $path . '.png';
    }
    public function join($request, $response,$args)
    {
        $evenement_id=$args['id_evenement'];
        $evenement=Evenement::find($args["id_evenement"]);
        $epreuves = $evenement->epreuves()->get()->toArray();
        $evenement= $evenement->toArray();
        if ($request->isPost()) {
            $nom = $request->getParam('nom');
            $prenom = $request->getParam('prenom');
            $email = $request->getParam('email');
            $birthday = $request->getParam('birthday');
            $epreuvesSelection = $request->getParam('epreuves');
            $validation = $this->validator->validate($request, [
                'nom' => V::notEmpty()->length(1,50),
                'prenom' => V::notEmpty()->length(1,50),
                'email' => V::notEmpty()->noWhitespace()->email(),
                'birthday' => v::notEmpty()->date('d/m/Y'),
            ]);


            if ($validation->isValid()) {
                /*Test si pas deja inscrit*/
                $sportif = Sportif::where('email',$email)->first();
                if ($sportif==null) {
                    $birthday = \DateTime::createFromFormat("d-m-Y",$birthday);
                    $sportif=new Sportif();
                    $sportif->nom=$nom;
                    $sportif->prenom=$prenom;
                    $sportif->email=$email;
                    $sportif->birthday=$birthday;
                    $sportif->save();
                }
                $prixTotal=0;
                if (isset($epreuvesSelection)) {
                    foreach ($epreuvesSelection as $epreuve) {
                        try{
                            $sportif->epreuves()->attach($epreuve);
                            $prixTotal+=Epreuve::find($epreuve)->prix;
                        }
                        catch (QueryException $e){
                            $errorCode = $e->errorInfo[1];
                            if($errorCode == 1062){
                                $this->flash('error', 'Vous vous êtes déjà inscrit à l\'épreuve '.Epreuve::find($epreuve)->nom);
                                return $this->redirect($response, 'epreuve.join', ['id_evenement'=>$evenement_id]);
                            }
                        }
                    }
                }
                else {
                    $this->flash('error', 'Selectionnez au moins une épreuve');
                    return $this->redirect($response, 'epreuve.join', ['id_evenement'=>$evenement_id]);
                }

                return $this->view->render($response, 'Epreuve/payment.twig',compact('prixTotal','evenement_id'));
            }

        }
        return $this->view->render($response, 'Epreuve/join.twig', compact('evenement','epreuves'));
    }
}
