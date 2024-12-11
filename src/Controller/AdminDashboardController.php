<?php

namespace App\Controller;

use App\Entity\Review;
use App\Repository\CategoryRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin', name: 'admin_')]
class AdminDashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function index(
        UserRepository $userRepository,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository,
        OrderRepository $orderRepository
    ): Response {
        // Vérifiez que l'utilisateur est admin
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $userRepository->findAll();
        $categories = $categoryRepository->findAll();
        $products = $productRepository->findAll();
        $orders = $orderRepository->findAll();

        // Récupérez les données nécessaires
        return $this->render('admin_dashboard/index.html.twig', [
            'users' => $users,
            'categories' => $categories,
            'products' => $products,
            'orders' => $orders,
        ]);
    }

    #[Route('/order/details/{id}', name: 'order_details')]
    public function orderDetails(int $id, OrderRepository $orderRepository): Response
    {
        // Vérifiez que l'utilisateur est admin
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Recherchez la commande par son ID
        $order = $orderRepository->find($id);

        if (!$order) {
            throw $this->createNotFoundException('Commande non trouvée.');
        }

        // Passez les données à la vue
        return $this->render('admin_dashboard/order_details.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route('/reviews', name: 'reviews')]
    public function list(EntityManagerInterface $entityManager): Response
    {
        $reviews = $entityManager->getRepository(Review::class)->findBy(['isApproved' => false]);

        return $this->render('admin_dashboard/reviews.html.twig', [
            'reviews' => $reviews,
        ]);
    }

    #[Route('/review/{id}/approve', name: 'review_approve')]
    public function approve(Review $review, EntityManagerInterface $entityManager): Response
    {
        $review->setApproved(true);
        $entityManager->flush();

        $this->addFlash('success', 'L\'avis a été approuvé.');
        return $this->redirectToRoute('admin_reviews');
    }

    #[Route('/review/{id}/delete', name: 'review_delete')]
    public function delete(Review $review, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($review);
        $entityManager->flush();

        $this->addFlash('success', 'L\'avis a été supprimé.');
        return $this->redirectToRoute('admin_reviews');
    }
}
