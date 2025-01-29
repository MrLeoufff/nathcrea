<?php
namespace App\Controller;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

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
    public function handleWebhook(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $this->logger->info('Webhook PayPal reçu :', $data);

        if (! isset($data['event_type']) || ! isset($data['resource'])) {
            return new Response('Données invalides', Response::HTTP_BAD_REQUEST);
        }

        $eventType = $data['event_type'];
        $resource  = $data['resource'];

        switch ($eventType) {
            case 'CHECKOUT.ORDER.APPROVED':
                $this->logger->info("Commande PayPal approuvée : " . $resource['id']);
                break;

            case 'CHECKOUT.ORDER.COMPLETED':
            case 'PAYMENT.CAPTURE.COMPLETED':
                $this->logger->info("Paiement complété pour la commande PayPal : " . $resource['id']);

                // Mettre à jour la commande en base de données
                $order = $this->entityManager->getRepository(Order::class)->findOneBy(['paypalOrderId' => $resource['id']]);
                if ($order) {
                    $order->setStatus('completed');
                    $this->entityManager->flush();
                    $this->logger->info("Statut de la commande mis à jour en 'completed'.");
                }
                break;

            case 'PAYMENT.CAPTURE.DENIED':
                $this->logger->error("Paiement refusé pour la commande PayPal : " . $resource['id']);
                break;

            case 'PAYMENT.CAPTURE.REFUNDED':
                $this->logger->info("Paiement remboursé pour la commande PayPal : " . $resource['id']);
                break;

            default:
                $this->logger->warning("Événement PayPal non géré : " . $eventType);
        }

        return new Response('Webhook reçu', Response::HTTP_OK);
    }
}
