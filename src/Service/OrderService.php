<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class OrderService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Créer une commande pour un utilisateur donné.
     */
    public function createOrder(User $user, array $cartSummary, string $paypalOrderId): Order
    {
        // Création de la commande principale
        $order = new Order();
        $order->setOrderNumber($this->generateOrderNumber());
        $order->setUser($user);
        $order->setPaypalOrderId($paypalOrderId);
        $order->setTotalAmount($cartSummary['total']);
        $order->setStatus('pending');
        $order->setCreatedAt(new \DateTimeImmutable());

        // Ajoutez les articles du panier à la commande
        foreach ($cartSummary['items'] as $item) {
            $product = $item['product'];
            $quantity = $item['quantity'];

            if ($product->getStock() < $quantity) {
                throw new \InvalidArgumentException('Stock insuffisant pour le produit ' . $product->getName());
            }

            $product->setStock($product->getStock() - $quantity);

            $orderItem = new OrderItem();
            $orderItem->setProduct($item['product']);
            $orderItem->setProductName($item['product']->getName()); // Ajout du nom du produit
            $orderItem->setQuantity($item['quantity']);
            $orderItem->setUnitPrice($item['unit_price']);
            $orderItem->setTotalPrice($item['unit_price'] * $item['quantity']);
            $orderItem->setOrderRef($order); // Lier l'article à la commande

            // Persist chaque article
            $this->entityManager->persist($orderItem);

            // Ajouter l'article à la collection d'articles de la commande
            $order->addOrderItem($orderItem);
        }

        // Persist de la commande principale
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    /**
     * Génère un numéro de commande unique.
     */
    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORDER_' . strtoupper(uniqid());
        } while ($this->entityManager->getRepository(Order::class)->findOneBy(['orderNumber' => $orderNumber]));

        return $orderNumber;
    }

}
