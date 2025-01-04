<?php

namespace App\Service;

use App\Entity\User;
use App\Service\InvoiceGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;

class ForgetMeService
{
    private InvoiceGenerator $invoiceGenerator;
    private Filesystem $filesystem;
    private string $archiveDir;

    public function __construct(InvoiceGenerator $invoiceGenerator, string $archiveDir)
    {
        $this->invoiceGenerator = $invoiceGenerator;
        $this->filesystem = new Filesystem();
        $this->archiveDir = $archiveDir;
    }

    public function archiveUserInvoices(User $user): string
    {
        $userDir = $this->archiveDir . '/' . $user->getPseudo();
        $this->filesystem->mkdir($userDir);

        foreach ($user->getOrders() as $order) {
            $items = array_map(function ($orderItem) {
                return [
                    'name' => $orderItem->getProductName(),
                    'quantity' => $orderItem->getQuantity(),
                    'unit_price' => $orderItem->getUnitPrice(),
                    'total_price' => $orderItem->getTotalPrice(),
                ];
            }, $order->getOrderItems()->toArray());

            $invoicePath = $this->invoiceGenerator->generateInvoice([
                'id' => $order->getId(),
                'date' => $order->getCreatedAt(),
                'customer' => [
                    'name' => $user->getPseudo(),
                    'email' => $user->getEmail(),
                ],
                'items' => $items,
                'total' => $order->getTotalAmount(),
            ]);

            $this->filesystem->copy($invoicePath, $userDir . '/' . basename($invoicePath));
        }

        return $userDir;
    }

    public function deleteUserData(User $user, EntityManagerInterface $entityManager): void
    {
        // Supprimer les commandes associées à l'utilisateur sans supprimer les articles
        foreach ($user->getOrders() as $order) {
            foreach ($order->getOrderItems() as $orderItem) {
                $orderItem->setProduct(null); // Dissociation des produits dans les items
                $entityManager->persist($orderItem); // Sauvegarde de la dissociation
            }

            $entityManager->remove($order); // Suppression des commandes
        }

        // Supprimer les avis associés
        foreach ($user->getReviews() as $review) {
            $review->setUser(null); // Dissociation des avis
            $entityManager->remove($review);
        }

        // Supprimer l'utilisateur
        $entityManager->remove($user);
        $entityManager->flush(); // Exécute toutes les suppressions
    }

}
