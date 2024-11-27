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
        $cartItems = $this->getCartItems($entityManager);
        $total = 0;

        foreach ($cartItems as $item) {
            if (!is_object($item['product']) || !method_exists($item['product'], 'getPrice')) {
                throw new \LogicException('Invalid product in cart item: ' . json_encode($item));
            }
            if (!is_int($item['quantity'])) {
                throw new \LogicException('Invalid quantity in cart item: ' . json_encode($item));
            }

            $total += $item['product']->getPrice() * $item['quantity'];
        }

        return $total;
    }

    public function cleanCart(): void
    {
        $cart = $this->session->get('cart', []);
        foreach ($cart as $productId => $quantity) {
            if (!is_int($quantity) || $quantity <= 0) {
                unset($cart[$productId]);
            }
        }
        $this->session->remove('cart');
    }
}
