<?php

namespace App\Controller;

use App\Entity\Product;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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

    #[Route('/validate', name: 'cart_validate')]
    public function validateCart(EntityManagerInterface $entityManager, \App\Service\PayPalService $payPalService): Response
    {
        // Récupérer les articles du panier
        $cartItems = $this->cartService->getCartItems($entityManager);
        $total = $this->cartService->getTotal($entityManager);

        if (empty($cartItems)) {
            $this->addFlash('error', 'Votre panier est vide.');
            return $this->redirectToRoute('cart_index');
        }

        // Construire les données pour PayPal
        $purchaseUnits = [];
        foreach ($cartItems as $item) {
            $purchaseUnits[] = [
                'description' => $item['product']->getName(),
                'amount' => [
                    'currency_code' => 'EUR', // Remplacez par la devise de votre choix
                    'value' => number_format($item['product']->getPrice(), 2, '.', ''), // Format correct pour PayPal
                ],
            ];
        }

        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => $purchaseUnits,
            'application_context' => [
                'cancel_url' => $this->generateUrl('cart_index', [], 0),
                'return_url' => $this->generateUrl('cart_payment_success', [], 0),
            ],
        ];

        try {
            // Création de la commande via le service PayPal
            $client = $payPalService->getClient();
            $request = new \PayPalCheckoutSdk\Orders\OrdersCreateRequest();
            $request->prefer('return=representation');
            $request->body = $orderData;

            $response = $client->execute($request);
            $orderId = $response->result->id;

            // Redirection vers PayPal
            return $this->redirect("https://www.sandbox.paypal.com/checkoutnow?token=$orderId");
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la validation du panier : ' . $e->getMessage());
            return $this->redirectToRoute('cart_index');
        }
    }

}
