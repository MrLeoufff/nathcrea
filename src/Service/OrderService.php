<?php

namespace App\Service;

use App\Entity\Order;
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
    public function createOrder(User $user): Order
    {
        $order = new Order();

        // Générer un numéro unique
        $orderNumber = $this->generateOrderNumber();
        $order->setOrderNumber($orderNumber);
        $order->setUser($user);
        $order->setStatus('pending'); // Statut par défaut

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
