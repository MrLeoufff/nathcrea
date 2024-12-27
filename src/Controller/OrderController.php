<?php

namespace App\Controller;

use App\Entity\Order;
use App\Service\CartService;
use App\Service\OrderService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class OrderController extends AbstractController
{
    private OrderService $orderService;
    private LoggerInterface $logger;
    private CartService $cartService;

    public function __construct(OrderService $orderService, LoggerInterface $logger, CartService $cartService)
    {
        $this->orderService = $orderService;
        $this->logger = $logger;
        $this->cartService = $cartService;
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
    public function createOrder(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser(); // Assurez-vous que l'utilisateur est connecté
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour passer une commande.');
        }

        // Récupérez le récapitulatif du panier
        $cartSummary = $this->cartService->getCartSummary($entityManager);

        // Fournissez un ID PayPal fictif si ce n'est pas une commande PayPal
        $paypalOrderId = 'manual-' . uniqid();

        // Créez la commande via le service
        $order = $this->orderService->createOrder($user, $cartSummary, $paypalOrderId);

        $this->addFlash('success', "Commande créée avec succès : {$order->getOrderNumber()}");

        return $this->redirectToRoute('app_categories');
    }

    #[Route('/order/confirmation/{orderId}', name: 'app_order_confirmation')]
    public function orderConfirmation(EntityManagerInterface $entityManager, string $orderId): Response
    {
        $this->logger->info("Recherche de la commande avec ID ou référence : {$orderId}");

        $order = $entityManager->getRepository(Order::class)->find((int) $orderId);

        if (!$order) {
            $order = $entityManager->getRepository(Order::class)->findOneBy(['paypalOrderId' => $orderId]);
        }

        if (!$order) {
            $this->logger->error("Commande introuvable avec l'ID ou la référence : {$orderId}");
            throw $this->createNotFoundException("La commande avec l'ID ou la référence {$orderId} est introuvable.");
        }

        $user = $order->getUser();

        if (!$user) {
            $this->logger->error("Aucun utilisateur associé à la commande avec l'ID ou la référence : {$orderId}");
            throw $this->createNotFoundException("Aucun utilisateur associé à la commande.");
        }

        return $this->render('order/confirmation.html.twig', [
            'orderNumber' => $order->getOrderNumber(),
            'order' => $order,
            'user' => $user,
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
        $validStatuses = ['En attente', 'En cours', 'Envoyé', 'Terminé'];
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
