<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

#[Route('/admin', name: 'admin_')]
class AdminDashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function index(
        UserRepository $userRepository,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository
    ): Response
    {
        // Vérifiez que l'utilisateur est admin
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $userRepository->findAll();
        $categories = $categoryRepository->findAll();
        $products = $productRepository->findAll();

        // Récupérez les données nécessaires
        return $this->render('admin_dashboard/index.html.twig', [
            'users' => $users,
            'categories' => $categories,
            'products' => $products,
        ]);
    }
}
