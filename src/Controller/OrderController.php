<?php

namespace App\Controller;

use App\Entity\Order;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OrderController extends AbstractController
{
    private OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    #[Route('/orders', name: 'app_orders')]
    public function listOrders(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour voir les commandes.');
        }

        // Si l'utilisateur est administrateur, afficher toutes les commandes
        if ($this->isGranted('ROLE_ADMIN')) {
            $orders = $entityManager->getRepository(Order::class)->findAll();
        } else {
            // Sinon, afficher uniquement les commandes de l'utilisateur connecté
            $orders = $entityManager->getRepository(Order::class)->findBy(['user' => $user]);
        }

        return $this->render('order/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/order/create', name: 'order_create')]
    public function createOrder(): Response
    {
        $user = $this->getUser(); // Assurez-vous que l'utilisateur est connecté
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour passer une commande.');
        }

        // Créer une commande via le service
        $order = $this->orderService->createOrder($user);

        $this->addFlash('success', "Commande créée avec succès : {$order->getOrderNumber()}");

        return $this->redirectToRoute('app_categories');
    }

    #[Route('/order/confirmation/{orderId}', name: 'app_order_confirmation')]
    public function orderConfirmation(EntityManagerInterface $entityManager, int $orderId): Response
    {
        $order = $entityManager->getRepository(Order::class)->find($orderId);

        if (!$order) {
            throw $this->createNotFoundException("La commande avec l'ID {$orderId} est introuvable.");
        }

        return $this->render('order/confirmation.html.twig', [
            'orderNumber' => $order->getOrderNumber(),
            'order' => $order,
        ]);
    }

    #[Route('/order/{id}/update-status', name: 'order_update_status', methods: ['POST'])]
    public function updateStatus(int $id, EntityManagerInterface $entityManager, \Symfony\Component\HttpFoundation\Request $request): Response
    {
        $order = $entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            $this->addFlash('error', "Commande introuvable.");
            return $this->redirectToRoute('app_categories');
        }

        // Vérifiez si l'utilisateur est autorisé à effectuer cette action
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cette commande.');
        }

        // Récupérer le statut depuis la requête
        $newStatus = $request->request->get('status');

        // Valider le statut
        $validStatuses = ['PENDING', 'PROCESSING', 'SHIPPED', 'COMPLETED'];
        if (!in_array($newStatus, $validStatuses)) {
            $this->addFlash('error', 'Statut invalide.');
            return $this->redirectToRoute('app_order_confirmation', ['orderId' => $id]);
        }

        // Mettre à jour le statut de la commande
        $order->setStatus($newStatus);
        $entityManager->flush();

        $this->addFlash('success', "Le statut de la commande a été mis à jour avec succès.");
        return $this->redirectToRoute('app_order_confirmation', ['orderId' => $id]);
    }
}
