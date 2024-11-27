<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{

    #[Route('/', name: 'app_home')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $categories = $entityManager->getRepository(Category::class)->findAll();

        return $this->render('home/index.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/category/{id}', name: 'app_category_products')]
    public function categoryProducts(Category $category): Response
    {
        $products = $category->getProducts();

        return $this->render('home/category_products.html.twig', [
            'category' => $category,
            'products' => $products,
        ]);
    }

    #[Route('/product/{id}', name: 'app_product_detail')]
    public function productDetail(Product $product): Response
    {
        return $this->render('home/product_detail.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/order/{id}', name: 'app_order_product')]
    public function orderProduct(int $id, Product $product, EntityManagerInterface $entityManager, Request $request): Response
    {
        if (!$this->getUser()) {
            $this->addFlash('error', 'Vous devez être connecté pour passer une commande.');
            return $this->redirectToRoute('app_login');
        }

        // Ajouter le produit à la commande de l'utilisateur
        // Logique simplifiée : ajouter au panier ou créer une commande (selon votre implémentation)
        $this->addFlash('success', "Le produit {$product->getName()} a été ajouté à votre commande !");

        // Rediriger vers la page des produits ou du panier
        return $this->redirectToRoute('cart_add', ['id' => $id]);
    }
}
