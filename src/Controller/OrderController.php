<?php

namespace App\Controller;

use App\Entity\Product;
use App\Service\OrderService;
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

        return $this->redirectToRoute('cart_index');
    }
}
