<?php

namespace App\Service;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CartService
{
    private $session;

    public function __construct(RequestStack $requestStack)
    {
        $this->session = $requestStack->getSession();
    }

    public function addToCart(Product $product, int $quantity = 1): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('La quantité doit être supérieure à 0.');
        }

        $cart = $this->session->get('cart', []);

        $productId = $product->getId();

        if (isset($cart[$productId])) {
            $cart[$productId] += $quantity;
        } else {
            $cart[$productId] = $quantity;
        }

        $this->session->set('cart', $cart);

    }

    public function getCartItems(EntityManagerInterface $entityManager): array
    {
        $cart = $this->session->get('cart', []);
        $products = $entityManager->getRepository(Product::class)->findBy(['id' => array_keys($cart)]);

        $cartItems = [];
        foreach ($products as $product) {
            $productId = $product->getId();
            if (isset($cart[$productId]) && is_int($cart[$productId])) {
                $cartItems[] = [
                    'product' => $product,
                    'quantity' => $cart[$productId],
                ];
            }
        }

        return $cartItems;
    }

    public function removeFromCart(Product $product): void
    {
        $cart = $this->session->get('cart', []);
        $productId = $product->getId();

        if (isset($cart[$productId])) {
            unset($cart[$productId]);
        }

        $this->session->set('cart', $cart);
    }

    public function getCart(): array
    {
        return $this->session->get('cart', []);
    }

    public function getTotal(EntityManagerInterface $entityManager): float
    {
        $summary = $this->getCartSummary($entityManager);
        return $summary['total'];
    }

    public function getCartSummary(EntityManagerInterface $entityManager): array
    {
        $cart = $this->getCart();
        $cartItems = [];
        $total = 0;

        foreach ($cart as $productId => $quantity) {
            $product = $entityManager->getRepository(Product::class)->find($productId);

            if ($product && $quantity > 0) {
                $price = (float) $product->getPrice();

                if (!is_numeric($price)) {
                    throw new \LogicException(sprintf('Le prix du produit ID %d n\'est pas valide : %s', $product->getId(), json_encode($price)));
                }

                $cartItems[] = [
                    'name' => $product->getName(),
                    'description' => substr($product->getDescription(), 0, 127), // Limite PayPal
                    'unit_amount' => [
                        'currency_code' => 'EUR',
                        'value' => number_format((float) $price, 2, '.', ''), // Assurez-vous que le prix est un float
                    ],
                    'quantity' => (int) $quantity,
                ];

                $total += (float) $price * (int) $quantity;
            }

        }

        dump([
            'items' => $cartItems,
            'total' => $total,
        ]);

        return [
            'items' => $cartItems,
            'total' => number_format($total, 2, '.', ''), // Format PayPal
        ];
    }

    public function cleanCart(): void
    {
        $this->session->remove('cart');
    }

}
