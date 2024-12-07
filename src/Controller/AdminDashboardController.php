<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
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

}
