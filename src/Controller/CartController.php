<?php

namespace App\Controller;

use App\Entity\Product;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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

        // Vérification du stock
        $cartItems = $this->cartService->getCartItems($entityManager);
        $existingQuantity = $cartItems[$id]['quantity'] ?? 0;
        $requestedQuantity = $existingQuantity + 1;

        if ($product->getStock() < $requestedQuantity) {
            $this->addFlash('error', "Stock insuffisant pour le produit : {$product->getName()}");
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

    #[Route('/validate', name: 'cart_validate')]
    public function validateCart(EntityManagerInterface $entityManager, \App\Service\PayPalService $payPalService): Response
    {
        // Étape 1 : Récupérer les articles et total du panier
        $cartItems = $this->cartService->getCartItems($entityManager);
        $total = $this->cartService->getTotal($entityManager);

        // Vérifier si le panier est vide
        if (empty($cartItems)) {
            $this->addFlash('error', 'Votre panier est vide.');
            return $this->redirectToRoute('cart_index');
        }

        // Vérification du stock pour chaque produit
        foreach ($cartItems as $item) {
            $product = $item['product'];
            $quantity = $item['quantity'];

            if ($product->getStock() < $quantity) {
                $this->addFlash('error', "Stock insuffisant pour le produit : {$product->getName()}.");
                return $this->redirectToRoute('cart_index');
            }
        }

        // Étape 2 : Construire les unités d'achat (purchase_units)
        $purchaseUnits = [];
        foreach ($cartItems as $index => $item) {
            // Validation des données du produit
            if (!isset($item['product']) || !isset($item['quantity'])) {
                $this->addFlash('error', 'Données incorrectes dans le panier.');
                return $this->redirectToRoute('cart_index');
            }

            $purchaseUnits[] = [
                'reference_id' => "unit_" . ($index + 1), // Identifiant unique
                'description' => mb_substr($item['product']->getName(), 0, 127),
                'amount' => [
                    'currency_code' => 'EUR',
                    'value' => number_format($item['product']->getPrice() * $item['quantity'], 2, '.', ''),
                ],
                'quantity' => (string) $item['quantity'],
            ];
        }

        // Validation des totaux (optionnelle, pour éviter les incohérences)
        $totalCalculated = array_reduce($purchaseUnits, function ($carry, $unit) {
            return $carry + (float) $unit['amount']['value'];
        }, 0);

        if (number_format($total, 2, '.', '') != number_format($totalCalculated, 2, '.', '')) {
            $this->addFlash('error', 'Erreur dans le calcul des totaux.');
            return $this->redirectToRoute('cart_index');
        }

        // Étape 3 : Préparer les données pour PayPal
        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => $purchaseUnits,
            'application_context' => [
                'cancel_url' => $this->generateUrl('cart_payment_cancel', [], 0),
                'return_url' => $this->generateUrl('cart_payment_success', [], 0),
            ],
        ];

        try {
            // Étape 4 : Créer la commande avec PayPal
            $client = $payPalService->getClient();
            $request = new \PayPalCheckoutSdk\Orders\OrdersCreateRequest();
            $request->prefer('return=representation');
            $request->body = $orderData;

            $response = $client->execute($request);

            // Étape 5 : Récupérer l'ID de la commande et rediriger l'utilisateur
            $orderId = $response->result->id;

            return $this->redirect("https://www.sandbox.paypal.com/checkoutnow?token=$orderId");
        } catch (\Exception $e) {
            // Étape 6 : Gestion des erreurs
            $this->addFlash('error', 'Erreur lors de la validation du panier : ' . $e->getMessage());
            return $this->redirectToRoute('cart_index');
        }
    }

    #[Route('/api/add/{id}', name: 'api_cart_add', methods: ['POST'])]
    public function apiAdd(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $product = $entityManager->getRepository(Product::class)->find($id);

        if (!$product) {
            return new JsonResponse(['error' => 'Produit non trouvé.'], 404);
        }

        // Vérification du stock
        $cartItems = $this->cartService->getCartItems($entityManager);
        $existingQuantity = $cartItems[$id]['quantity'] ?? 0;
        $requestedQuantity = $existingQuantity + 1;

        if ($product->getStock() < $requestedQuantity) {
            return new JsonResponse(['error' => 'Stock insuffisant.'], 400);
        }

        $this->cartService->addToCart($product);

        return new JsonResponse([
            'success' => true,
            'message' => 'Produit ajouté au panier.',
            'cart' => $this->cartService->getCart(),
        ]);
    }

    #[Route('/api/remove/{id}', name: 'api_cart_remove', methods: ['POST'])]
    public function apiRemove(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $product = $entityManager->getRepository(Product::class)->find($id);

        if (!$product) {
            return new JsonResponse(['error' => 'Produit non trouvé.'], 404);
        }

        $this->cartService->removeFromCart($product);

        return new JsonResponse([
            'success' => true,
            'message' => 'Produit retiré du panier.',
            'cart' => $this->cartService->getCart(),
        ]);
    }

    #[Route('/api/cart/count', name: 'api_cart_count', methods: ['GET'])]
    public function getCartCount(): JsonResponse
    {
        $cart = $this->cartService->getCart();
        $totalQuantity = array_sum($cart);
        return new JsonResponse(['count' => $totalQuantity]);
    }

}
