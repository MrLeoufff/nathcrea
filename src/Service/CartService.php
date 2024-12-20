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

        if (!$this->isStockAvailable($product, $quantity)) {
            throw new \LogicException(sprintf('Stock insuffisant pour le produit : %s.', $product->getName()));
        }

        $cart = $this->session->get('cart', []);
        $productId = $product->getId();

        if (isset($cart[$productId])) {
            $newQuantity = $cart[$productId] + $quantity;

            if (!$this->isStockAvailable($product, $newQuantity)) {
                throw new \LogicException(sprintf('Stock insuffisant pour le produit : %s.', $product->getName()));
            }

            $cart[$productId] = $newQuantity;
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
            if ($product->getStock() > 0) {
                $productId = $product->getId();
                if (isset($cart[$productId]) && is_int($cart[$productId])) {
                    $cartItems[] = [
                        'product' => $product,
                        'quantity' => $cart[$productId],
                    ];
                }
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

            if (!$product || $quantity > $product->getStock()) {
                throw new \LogicException("Le produit avec l'ID {$productId} est invalide ou en rupture de stock.");
            }

            $price = $product->getPrice();

            $cartItems[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $price,
                'total' => $price * $quantity,
            ];

            $total += $price * $quantity;
        }

        return [
            'items' => $cartItems,
            'total' => number_format($total, 2, '.', ''),
        ];
    }

    public function cleanCart(): void
    {
        $this->session->remove('cart');
    }

    public function isStockAvailable(Product $product, int $quantity): bool
    {
        return $product->getStock() >= $quantity;
    }

    public function getTotalQuantity(): int
    {
        return array_sum($this->getCart());
    }
}
