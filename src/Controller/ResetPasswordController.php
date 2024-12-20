<?php

namespace App\Controller;

use App\Form\ResetPasswordRequestType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ResetPasswordController extends AbstractController
{
    #[Route('/reset-password', name: 'app_reset_password')]
    public function request(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        $form = $this->createForm(ResetPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('danger', 'Veuillez fournir une adresse email valide.');
                return $this->redirectToRoute('app_reset_password');
            }

            $user = $userRepository->findOneBy(['email' => $email]);

            if ($user) {
                // Générer un token et une date d'expiration
                $resetToken = $user->generateResetToken();
                $user->setTokenExpiresAt(new \DateTime('+1 hour'));
                $entityManager->flush();

                // Créer un email
                $email = (new Email())
                    ->from('nathcrea.app@gmail.com')
                    ->to($user->getEmail())
                    ->subject('Réinitialisation de mot de passe')
                    ->html($this->renderView('emails/reset_password.html.twig', [
                        'user' => $user,
                        'url' => $this->generateUrl('app_reset_password_reset', ['token' => $resetToken], UrlGeneratorInterface::ABSOLUTE_URL),
                    ]));

                $mailer->send($email);

                $this->addFlash('success', 'Un email de réinitialisation a été envoyé.');
            } else {
                $this->addFlash('danger', 'Aucun utilisateur trouvé avec cet email.');
            }
        }

        return $this->render('reset_password/request.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password_reset')]
    public function reset(
        string $token,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $userRepository->findOneBy(['confirmationToken' => $token]);

        // Vérifier la validité du token et sa date d'expiration
        if (!$user || $user->getTokenExpiresAt() < new \DateTime()) {
            $this->addFlash('danger', 'Le lien de réinitialisation est invalide ou a expiré.');
            return $this->redirectToRoute('app_reset_password');
        }

        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');

            if (empty($newPassword) || strlen($newPassword) < 8) {
                $this->addFlash('danger', 'Le mot de passe doit contenir au moins 8 caractères.');
                return $this->redirectToRoute('app_reset_password_reset', ['token' => $token]);
            }

            // Hashage du mot de passe
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $user->setConfirmationToken(null);
            $user->setTokenExpiresAt(null);
            $entityManager->flush();

            $this->addFlash('success', 'Mot de passe réinitialisé avec succès.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'token' => $token,
        ]);
    }
}
