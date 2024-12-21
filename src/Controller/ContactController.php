<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
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
            try {
                $email = (new Email())
                    ->from('nathcrea.app@gmail.com')
                    ->replyTo($request->request->get('email')) // Adresse fournie dans le formulaire
                    ->to('nathcrea.app@gmail.com')
                    ->subject('Demande de contact')
                    ->text(sprintf(
                        "Nom : %s\nEmail : %s\nMessage : %s",
                        $request->request->get('name') ?? 'Non spécifié',
                        $request->request->get('email') ?? 'Non spécifié',
                        $request->request->get('message') ?? 'Non spécifié'
                    ));

                $mailer->send($email);
                $this->addFlash('success', 'Votre demande a bien été envoyée.');
            } catch (TransportExceptionInterface $e) {
                // Loggez l'erreur pour analyser le problème
                $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi.');
            }

            // Redirection pour éviter le renvoi du formulaire
            return $this->redirectToRoute('app_home');
        }

        // Rendu de la vue avec le formulaire de contact
        return $this->render('contact/index.html.twig');
    }
}
