<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Service\CartService;
use App\Service\InvoiceGenerator;
use App\Service\PayPalService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

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
    public function createPayment(EntityManagerInterface $entityManager, LoggerInterface $logger): Response
    {
        $cartSummary = $this->cartService->getCartSummary($entityManager);
        $logger->info('Cart Summary: ' . json_encode($cartSummary));

        // Construire les données de la commande pour PayPal
        $orderData = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'description' => 'Votre panier',
                    'amount' => [
                        'currency_code' => 'EUR',
                        'value' => $cartSummary['total'], // Total global
                        'breakdown' => [
                            'item_total' => [
                                'currency_code' => 'EUR',
                                'value' => $cartSummary['total'], // Total des articles
                            ],
                        ],
                    ],
                    'items' => array_map(function ($item) {
                        return [
                            'name' => $item['product']->getName(),
                            'description' => $item['product']->getDescription(),
                            'unit_amount' => [
                                'currency_code' => 'EUR',
                                'value' => number_format($item['product']->getPrice(), 2, '.', ''),
                            ],
                            'quantity' => $item['quantity'],
                        ];
                    }, $this->cartService->getCartItems($entityManager)),
                ],
            ],
            'application_context' => [
                'cancel_url' => $this->generateUrl('cart_payment_cancel', [], 0),
                'return_url' => $this->generateUrl('cart_payment_success', [], 0),
            ],
        ];

        $logger->info('PayPal Order Data: ' . json_encode($orderData));

        try {
            $client = $this->payPalService->getClient();
            $request = new \PayPalCheckoutSdk\Orders\OrdersCreateRequest();
            $request->prefer('return=representation');
            $request->body = $orderData;

            $response = $client->execute($request);

            // Logguez la réponse
            dump($response);

            if (isset($response->result->id)) {
                $orderId = $response->result->id;
                return $this->redirect("https://www.sandbox.paypal.com/checkoutnow?token=$orderId");
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur PayPal : ' . $e->getMessage());

            // Logguez les détails de l'erreur
            dump($e->getMessage());
            dump($e->getTraceAsString());
        }

        return $this->redirectToRoute('cart_index');
    }

    #[Route('/payment/success', name: 'cart_payment_success')]
    public function paymentSuccess(
        EntityManagerInterface $entityManager,
        Request $request,
        PayPalService $payPalService,
        LoggerInterface $logger,
        InvoiceGenerator $invoiceGenerator,
        MailerInterface $mailer
    ): Response {
        $orderId = $request->query->get('token');
        if (!$orderId) {
            $this->addFlash('error', 'Identifiant de commande introuvable.');
            return $this->redirectToRoute('cart_index');
        }

        $logger->info('Traitement du paiement pour la commande PayPal : ' . $orderId);

        try {
            $client = $payPalService->getClient();
            $captureRequest = new \PayPalCheckoutSdk\Orders\OrdersCaptureRequest($orderId);
            $captureRequest->prefer('return=representation');
            $response = $client->execute($captureRequest);
            $logger->info('Réponse PayPal reçue : ' . json_encode($response->result));

            if ($response->result->status === 'COMPLETED') {
                // Créez une nouvelle commande
                $order = new Order();
                $order->setOrderNumber(Uuid::v4()->toRfc4122());
                $order->setPaypalOrderId($response->result->id);
                $order->setStatus($response->result->status);
                $order->setTotalAmount(array_sum(array_map(
                    fn($unit) => (float) $unit->amount->value,
                    $response->result->purchase_units
                )));
                $order->setUser($this->getUser());
                $entityManager->persist($order);

                // Parcourir les unités d'achat et mettre à jour le stock
                foreach ($response->result->purchase_units as $unit) {
                    $description = $unit->description ?? null;
                    $amountValue = $unit->amount->value ?? null;

                    if (!$description || !$amountValue) {
                        throw new \Exception('Données manquantes pour l\'unité d\'achat : ' . json_encode($unit));
                    }

                    // Rechercher le produit dans la base de données
                    $product = $entityManager->getRepository(Product::class)->findOneBy(['name' => $description]);

                    if (!$product) {
                        throw new \Exception('Produit introuvable dans la base de données : ' . $description);
                    }

                    // Vérifier la quantité commandée
                    $cartItems = $this->cartService->getCartItems($entityManager);
                    $cartItem = array_filter($cartItems, fn($item) => $item['product']->getName() === $description);

                    if (empty($cartItem)) {
                        throw new \Exception('Impossible de trouver l\'article correspondant dans le panier : ' . $description);
                    }

                    $quantity = (int) $cartItem[array_key_first($cartItem)]['quantity'];

                    // Vérifier et mettre à jour le stock
                    $newStock = $product->getStock() - $quantity;
                    if ($newStock < 0) {
                        throw new \Exception('Stock insuffisant pour le produit ' . $product->getName());
                    }
                    $product->setStock($newStock);

                    // Ajouter l'article à la commande
                    $orderItem = new OrderItem();
                    $orderItem->setProductName($product->getName());
                    $orderItem->setQuantity($quantity);
                    $orderItem->setUnitPrice((float) $amountValue / $quantity);
                    $orderItem->setTotalPrice((float) $amountValue);
                    $orderItem->setOrderRef($order);
                    $entityManager->persist($orderItem);
                }

                // Sauvegarder la commande et vider le panier
                $entityManager->flush();
                $this->cartService->cleanCart();

                // Générer la facture
                $invoiceItems = array_map(function ($item) {
                    return [
                        'name' => $item['product']->getName(),
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['product']->getPrice(),
                        'total_price' => $item['quantity'] * $item['product']->getPrice(),
                    ];
                }, $cartItems);

                $invoicePath = $invoiceGenerator->generateInvoice([
                    'id' => $order->getId(),
                    'date' => new \DateTime(),
                    'customer' => [
                        'name' => $this->getUser()->getPseudo(),
                        'email' => $this->getUser()->getEmail(),
                    ],
                    'items' => $invoiceItems,
                    'total' => $order->getTotalAmount(),
                ]);

                // Envoyer la facture par email
                $email = (new Email())
                    ->from('developpeur.web.gard@gmail.com')
                    ->to($this->getUser()->getEmail())
                    ->subject('Confirmation de commande et facture')
                    ->text('Merci pour votre commande. Veuillez trouver votre facture en pièce jointe.')
                    ->attachFromPath($invoicePath);

                $mailer->send($email);

                $logger->info('Commande enregistrée et panier vidé.');
                $this->addFlash('success', 'Votre paiement a été validé avec succès.');
                return $this->redirectToRoute('app_order_confirmation', ['orderId' => $order->getId()]);
            }

            throw new \Exception("Le paiement n'a pas été complété.");
        } catch (\Exception $e) {
            $logger->error('Erreur lors du traitement du paiement : ' . $e->getMessage());
            $this->addFlash('error', 'Erreur lors de la validation du paiement : ' . $e->getMessage());
            return $this->redirectToRoute('cart_index');
        }
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
