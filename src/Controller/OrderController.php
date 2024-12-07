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
}
