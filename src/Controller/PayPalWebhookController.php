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
    public function handleWebhook(Request $request, LoggerInterface $logger): Response
    {

        // 🟢 1️⃣ Vérifier si la requête arrive bien sur le serveur
        $this->logger->info('🚀 Webhook PayPal reçu avec cette requête brute : ' . $request->getContent());

        // 🟢 2️⃣ Vérifier si le JSON est valide
        $data = json_decode($request->getContent(), true);
        $logger->info('Webhook PayPal reçu : ' . json_encode($data));

        if (! is_array($data)) {
            return new Response('Données invalides : ' . $request->getContent(), Response::HTTP_BAD_REQUEST);
        }

        $response = new Response('Webhook reçu', Response::HTTP_OK);

        if (! $data) {
            $this->logger->error('❌ Webhook PayPal reçu mais JSON invalide.');
            $response->setContent('Invalid JSON');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        } elseif (! isset($data['event_type']) || ! isset($data['resource'])) {
            $this->logger->error('❌ Données invalides dans le webhook PayPal : ', $data);
            $response->setContent('Données invalides');
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        } else {
            // 🟢 3️⃣ Log du type d'événement reçu
            $eventType = $data['event_type'];
            $resource  = $data['resource'];
            $this->logger->info("📌 Type d'événement reçu : " . $eventType);

            // 🟢 4️⃣ Vérifier que `resource['id']` est bien présent
            if (! isset($resource['id'])) {
                $this->logger->error("❌ L'événement PayPal $eventType ne contient pas d'ID de commande.");
                $response->setContent('Invalid event structure');
                $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            } else {
                switch ($eventType) {
                    case 'CHECKOUT.ORDER.APPROVED':
                        $this->logger->info("✅ Commande PayPal approuvée : " . $resource['id']);
                        break;

                    case 'CHECKOUT.ORDER.COMPLETED':
                    case 'PAYMENT.CAPTURE.COMPLETED':
                        $this->logger->info("✅ Paiement complété pour la commande PayPal : " . $resource['id']);

                        // 🔴 Vérifier si la commande existe en base
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
            }
        }

        return $response;
    }
}
