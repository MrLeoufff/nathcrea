<?php
namespace App\Controller;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PayPalWebhookController extends AbstractController
{
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;

    public function __construct(LoggerInterface $logger, EntityManagerInterface $entityManager)
    {
        $this->logger        = $logger;
        $this->entityManager = $entityManager;
    }

    #[Route('/webhook/paypal', name: 'paypal_webhook', methods: ['POST'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function handleWebhook(Request $request): Response
    {
        // 🟢 1️⃣ Vérifie si la requête arrive bien sur le serveur
        $requestContent = $request->getContent();
        $this->logger->info('🚀 Webhook PayPal reçu avec cette requête brute : ' . $requestContent);

        // 🟢 2️⃣ Vérifie si le JSON est valide
        $data = json_decode($requestContent, true);
        if (! $data || ! isset($data['event_type']) || ! isset($data['resource'])) {
            $this->logger->error('❌ Webhook PayPal reçu mais JSON invalide ou incomplet.', [
                'raw_data' => $requestContent,
            ]);
            return new Response('Données invalides', Response::HTTP_BAD_REQUEST);
        }

        // 🟢 3️⃣ Log du type d'événement reçu
        $eventType = $data['event_type'];
        $resource  = $data['resource'];
        $this->logger->info("📌 Type d'événement reçu : $eventType");

        // 🟢 4️⃣ Vérifie que l'ID de commande PayPal est bien présent
        if (! isset($resource['id'])) {
            $this->logger->error("❌ L'événement PayPal $eventType ne contient pas d'ID de commande.");
            return new Response('Invalid event structure', Response::HTTP_BAD_REQUEST);
        }

        // 🟢 5️⃣ Gestion des différents événements PayPal
        switch ($eventType) {
            case 'CHECKOUT.ORDER.APPROVED':
                $this->logger->info("✅ Commande PayPal approuvée : " . $resource['id']);
                break;

            case 'CHECKOUT.ORDER.COMPLETED':
            case 'PAYMENT.CAPTURE.COMPLETED':
                $this->logger->info("✅ Paiement complété pour la commande PayPal : " . $resource['id']);

                // 🔴 Vérifie si la commande existe en base
                $order = $this->entityManager->getRepository(Order::class)->findOneBy(['paypalOrderId' => $resource['id']]);
                if ($order) {
                    $order->setStatus('completed');
                    $this->entityManager->flush();
                    $this->logger->info("✅ Statut de la commande mis à jour en 'completed'.");
                } else {
                    $this->logger->error("❌ Commande introuvable pour PayPalOrderID : " . $resource['id']);
                }
                break;

            case 'PAYMENT.CAPTURE.DENIED':
                $this->logger->error("⚠️ Paiement refusé pour la commande PayPal : " . $resource['id']);
                break;

            case 'PAYMENT.CAPTURE.REFUNDED':
                $this->logger->info("ℹ️ Paiement remboursé pour la commande PayPal : " . $resource['id']);
                break;

            default:
                $this->logger->warning("❓ Événement PayPal non géré : " . $eventType);
        }

        return new Response('Webhook reçu et traité', Response::HTTP_OK);
    }
}
