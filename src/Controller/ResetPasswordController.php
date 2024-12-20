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
use Symfony\Component\Routing\Annotation\Route;

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
            $user = $userRepository->findOneBy(['email' => $email]);

            if ($user) {
                $resetToken = $user->generateResetToken();
                $entityManager->flush();

                $email = (new Email())
                    ->from('nathcrea.app@gmail.com')
                    ->to($user->getEmail())
                    ->subject('Réinitialisation de mot de passe')
                    ->html('<p>Pour réinitialiser votre mot de passe, cliquez sur ce lien :</p>
                            <a href="' . $this->generateUrl('app_reset_password_reset', ['token' => $resetToken], true) . '">Réinitialiser mon mot de passe</a>');

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
    public function reset(string $token, Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        $user = $userRepository->findOneBy(['confirmationToken' => $token]);

        if (!$user) {
            throw $this->createNotFoundException('Token invalide.');
        }

        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            $user->setPassword(password_hash($newPassword, PASSWORD_BCRYPT));
            $user->setConfirmationToken(null);
            $entityManager->flush();

            $this->addFlash('success', 'Mot de passe réinitialisé avec succès.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('reset_password/reset.html.twig', [
            'token' => $token,
        ]);
    }
}
