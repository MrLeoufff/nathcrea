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
        $order = new Order();
        $order->setOrderNumber($this->generateOrderNumber());
        $order->setUser($user);
        $order->setPaypalOrderId($paypalOrderId);
        $order->setTotalAmount($cartSummary['total']);
        $order->setStatus('pending');
        $order->setCreatedAt(new \DateTimeImmutable());

        // Ajoutez les articles du panier à la commande
        foreach ($cartSummary['items'] as $item) {
            $orderItem = new OrderItem();
            $orderItem->setProduct($item['product']);
            $orderItem->setQuantity($item['quantity']);
            $orderItem->setUnitPrice($item['unit_price']);
            $orderItem->setTotalPrice($item['unit_price'] * $item['quantity']);
        }

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
