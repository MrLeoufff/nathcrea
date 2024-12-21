<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PersonalisationController extends AbstractController
{
    #[Route("/personalisation", name: "app_personalisation")]
    #[IsGranted("ROLE_USER")]
    public function index(
        Request $request,
        MailerInterface $mailer,
        EntityManagerInterface $entityManager
    ): Response {
        // Vérifiez si l'utilisateur est connecté
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour faire une demande de personnalisation.');
            return $this->redirectToRoute('app_login'); // Redirigez vers la page de connexion
        }

        // Récupération et validation de l'email de l'utilisateur
        $userEmail = $user->getEmail();
        if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
        }

        if ($request->isMethod('POST')) {
            $personalisationData = $request->request->all();

            // Création de l'email
            try {
                $email = (new Email())
                    ->from('nathcrea.app@gmail.com') // Adresse valide
                    ->replyTo($userEmail) // Adresse utilisateur pour répondre
                    ->to('nathcrea.app@gmail.com') // Adresse de destination
                    ->subject('Nouvelle demande de personnalisation')
                    ->text(sprintf(
                        "Type : %s\nTaille : %s\nCouleur : %s\nNotes : %s",
                        $personalisationData['type'] ?? 'Non spécifié',
                        $personalisationData['size'] ?? 'Non spécifié',
                        $personalisationData['color'] ?? 'Non spécifié',
                        $personalisationData['additional_notes'] ?? 'Aucune'
                    ));

                $mailer->send($email);
                $this->addFlash('success', 'Votre demande a bien été envoyée.');
            } catch (TransportExceptionInterface $e) {
                // Loggez l'erreur pour analyser le problème
                $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi.');
            }

            // Redirection vers une route
            return $this->redirectToRoute('app_home');
        }

        // Rendu du formulaire de personnalisation
        return $this->render('personalisation/index.html.twig');
    }
}
