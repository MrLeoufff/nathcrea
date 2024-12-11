<?php

namespace App\Controller;

use App\Entity\Review;
use App\Form\ReviewType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReviewController extends AbstractController
{
    #[Route('/review/new', name: 'app_review_new')]
    public function addReview(Request $request, EntityManagerInterface $entityManager): Response
    {
        $review = new Review();
        $review->setUser($this->getUser()); // L'utilisateur connecté
        $review->setCreatedAt(new \DateTimeImmutable());
        $review->setApproved(false); // Les avis doivent être validés par un admin

        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($review);
            $entityManager->flush();

            $this->addFlash('success', 'Votre avis a été soumis et est en attente de validation.');

            return $this->redirectToRoute('app_home');
        }

        return $this->render('review/new.html.twig', [
            'reviewForm' => $form->createView(),
        ]);
    }
}
