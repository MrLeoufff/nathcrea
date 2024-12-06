<?php

namespace App\Controller;

use App\Service\CartService;
use App\Service\PayPalService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends AbstractController
{
    private PayPalService $payPalService;
    private CartService $cartService;

    private function validateOrderData(array $orderData): void
    {
        // Vérifier si le champ 'intent' est valide
        if (!isset($orderData['intent']) || !in_array($orderData['intent'], ['CAPTURE', 'AUTHORIZE'], true)) {
            throw new \InvalidArgumentException('Le champ "intent" est invalide ou manquant.');
        }

        // Vérifier les unités d'achat
        if (!isset($orderData['purchase_units']) || !is_array($orderData['purchase_units']) || empty($orderData['purchase_units'])) {
            throw new \InvalidArgumentException('Le champ "purchase_units" est invalide ou manquant.');
        }

        foreach ($orderData['purchase_units'] as $unit) {
            if (!isset($unit['description']) || empty($unit['description'])) {
                throw new \InvalidArgumentException('La description de l\'unité d\'achat est manquante.');
            }

            if (!isset($unit['amount']['currency_code']) || empty($unit['amount']['currency_code'])) {
                throw new \InvalidArgumentException('Le champ "currency_code" est manquant.');
            }

            if (!isset($unit['amount']['value']) || !is_numeric($unit['amount']['value']) || $unit['amount']['value'] <= 0) {
                throw new \InvalidArgumentException('Le montant de l\'unité d\'achat est invalide.');
            }
        }

        // Vérifier les URLs
        if (!isset($orderData['application_context']['cancel_url']) || !isset($orderData['application_context']['return_url'])) {
            throw new \InvalidArgumentException('Les URLs de retour ou d\'annulation sont manquantes.');
        }
    }

    public function __construct(PayPalService $payPalService, CartService $cartService)
    {
        $this->payPalService = $payPalService;
        $this->cartService = $cartService;
    }

    #[Route('/payment', name: 'app_payment')]
    public function createPayment(): Response
    {
        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'description' => 'Achat de test',
                    'amount' => [
                        'currency_code' => 'EUR',
                        'value' => '50.00',
                    ],
                ],
            ],
            'application_context' => [
                'cancel_url' => $this->generateUrl('cart_payment_cancel', [], 0),
                'return_url' => $this->generateUrl('cart_payment_success', [], 0),
            ],
        ];

        try {
            $this->validateOrderData($orderData);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', 'Données de commande invalides : ' . $e->getMessage());
            return $this->redirectToRoute('cart_index');
        }

        try {
            $client = $this->payPalService->getClient();
            $request = new \PayPalCheckoutSdk\Orders\OrdersCreateRequest();
            $request->prefer('return=representation');
            $request->body = $orderData;

            $response = $client->execute($request);
            $orderId = $response->result->id;

            return $this->redirect("https://www.sandbox.paypal.com/checkoutnow?token=$orderId");
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur PayPal : ' . $e->getMessage());
            return $this->redirectToRoute('app_payment_error');
        }
    }

    #[Route('/payment/success', name: 'cart_payment_success')]
    public function paymentSuccess(Request $request): Response
    {
        $orderId = $request->query->get('token'); // Récupère le token de commande PayPal

        if (!$orderId) {
            $this->addFlash('error', 'Aucun identifiant de commande trouvé.');
            return $this->redirectToRoute('cart_index');
        }

        try {
            // Récupération du client PayPal
            $client = $this->payPalService->getClient();

            // Validation de la commande avec PayPal
            $captureRequest = new \PayPalCheckoutSdk\Orders\OrdersCaptureRequest($orderId);
            $captureRequest->prefer('return=representation');

            $response = $client->execute($captureRequest);

            if ($response->statusCode === 201 && $response->result->status === 'COMPLETED') {
                // Paiement validé
                $this->cartService->cleanCart(); // Vide le panier
                $this->addFlash('success', 'Votre paiement a été validé avec succès.');
                return $this->redirectToRoute('app_order_confirmation', [
                    'orderId' => $orderId,
                ]);
            } else {
                // Le paiement n'est pas validé
                $this->addFlash('error', 'Le paiement n\'a pas été validé.');
            }
        } catch (\Exception $e) {
            // Gestion des erreurs
            $this->addFlash('error', 'Erreur lors de la validation du paiement : ' . $e->getMessage());
        }

        return $this->redirectToRoute('cart_index');
    }

    #[Route('/payment/cancel', name: 'cart_payment_cancel')]
    public function paymentCancel(): Response
    {
        $this->addFlash('error', 'Le paiement a été annulé.');
        return $this->redirectToRoute('cart_index');
    }

    #[Route('/payment/error', name: 'app_payment_error')]
    public function error(): Response
    {
        return $this->render('payment/error.html.twig', [
            'message' => 'Une erreur est survenue lors du paiement.',
        ]);
    }
}
