<?php

namespace App\Controller;

use App\Entity\Review;
use App\Form\ReviewType;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ReviewController extends AbstractController
{
    #[Route('/review/list', name: 'app_review_list')]
    public function listReviews(ReviewRepository $reviewRepository): Response
    {
        // Récupérer tous les avis depuis la base de données
        $reviews = $reviewRepository->findAll();

        // Retourner les avis dans une vue Twig
        return $this->render('review/list.html.twig', [
            'reviews' => $reviews,
        ]);
    }

    #[Route('/review/approve/{id}', name: 'app_review_approve', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function approveReview(Review $review, EntityManagerInterface $entityManager): RedirectResponse
    {
        // Valider l'avis
        $review->setApproved(true);
        $entityManager->flush();

        $this->addFlash('success', 'L\'avis a été approuvé avec succès.');

        return $this->redirectToRoute('app_review_list');
    }

    #[Route('/review/delete/{id}', name: 'app_review_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function deleteReview(Review $review, EntityManagerInterface $entityManager): RedirectResponse
    {
        // Supprimer l'avis
        $entityManager->remove($review);
        $entityManager->flush();

        $this->addFlash('success', 'L\'avis a été supprimé avec succès.');

        return $this->redirectToRoute('app_review_list');
    }

    #[Route('/review/new', name: 'app_review_new')]
    public function addReview(Request $request, EntityManagerInterface $entityManager): Response
    {
        $review = new Review();
        $review->setUser($this->getUser());
        $review->setCreatedAt(new \DateTimeImmutable());
        $review->setApproved(false);

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
