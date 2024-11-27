<?php

namespace App\Controller;

use App\Entity\Product;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/cart')]
class CartController extends AbstractController
{
    private CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    #[Route('/', name: 'cart_index')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $cartItems = $this->cartService->getCartItems($entityManager);
        $total = $this->cartService->getTotal($entityManager);

        return $this->render('cart/index.html.twig', [
            'cartItems' => $cartItems,
            'total' => $total,
        ]);
    }

    #[Route('/add/{id}', name: 'cart_add')]
    public function add(int $id, EntityManagerInterface $entityManager): Response
    {
        $product = $entityManager->getRepository(Product::class)->find($id);

        if (!$product) {
            $this->addFlash('error', "Produit non trouvé.");
            return $this->redirectToRoute('cart_index');
        }

        $this->cartService->addToCart($product);

        $this->addFlash('success', "Produit ajouté au panier : {$product->getName()}");

        return $this->redirectToRoute('cart_index');
    }

    #[Route('/remove/{id}', name: 'cart_remove')]
    public function remove(int $id, EntityManagerInterface $entityManager): Response
    {
        $product = $entityManager->getRepository(Product::class)->find($id);

        if (!$product) {
            $this->addFlash('error', "Produit non trouvé.");
            return $this->redirectToRoute('cart_index');
        }

        $this->cartService->removeFromCart($product);

        $this->addFlash('success', "Produit retiré du panier : {$product->getName()}");

        return $this->redirectToRoute('cart_index');
    }

    #[Route('/clear', name: 'cart_clear')]
    public function clear(): Response
    {
        $this->cartService->cleanCart();

        $this->addFlash('success', 'Le panier a été vidé.');

        return $this->redirectToRoute('cart_index');
    }
}
