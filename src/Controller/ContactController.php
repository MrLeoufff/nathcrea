<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class ContactController extends AbstractController
{
    #[Route("/contact", name: "app_contact")]
    public function index(Request $request, MailerInterface $mailer): Response
    {
        if ($request->isMethod('POST')) {
            $contactData = $request->request->all();

            // Récupération des champs du formulaire
            $name = $contactData['name'] ?? 'Anonyme';
            $emailAddress = $contactData['email'] ?? 'non-renseigné@example.com';
            $message = $contactData['message'] ?? 'Aucun message fourni';

            // Création de l'email
            $email = (new Email())
                ->from($emailAddress)
                ->to('nathcrea.app@gmail.com') // Remplacez par l'adresse cible
                ->subject("Message de contact de $name")
                ->text($message);

            // Envoi de l'email
            $mailer->send($email);

            // Ajout d'un message flash pour confirmer l'envoi
            $this->addFlash('success', 'Votre message a été envoyé avec succès.');

            // Redirection pour éviter le renvoi du formulaire
            return $this->redirectToRoute('app_contact');
        }

        // Rendu de la vue avec le formulaire de contact
        return $this->render('contact/index.html.twig');
    }
}
