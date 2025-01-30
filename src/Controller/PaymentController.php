<?php
namespace App\Controller;

use App\Service\CartService;
use App\Service\InvoiceGenerator;
use App\Service\OrderService;
use App\Service\PayPalRestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;

class PaymentController extends AbstractController
{
    private PayPalRestService $payPalRestService;
    private CartService $cartService;
    private OrderService $orderService;

    private function validateOrderData(array $orderData): void
    {
        // Vérifier si le champ 'intent' est valide
        if (! isset($orderData['intent']) || ! in_array($orderData['intent'], ['CAPTURE', 'AUTHORIZE'], true)) {
            throw new \InvalidArgumentException('Le champ "intent" est invalide ou manquant.');
        }

        // Vérifier les unités d'achat
        if (! isset($orderData['purchase_units']) || ! is_array($orderData['purchase_units']) || empty($orderData['purchase_units'])) {
            throw new \InvalidArgumentException('Le champ "purchase_units" est invalide ou manquant.');
        }

        foreach ($orderData['purchase_units'] as $unit) {
            if (! isset($unit['description']) || empty($unit['description'])) {
                throw new \InvalidArgumentException('La description de l\'unité d\'achat est manquante.');
            }

            if (! isset($unit['amount']['currency_code']) || empty($unit['amount']['currency_code'])) {
                throw new \InvalidArgumentException('Le champ "currency_code" est manquant.');
            }

            if (! isset($unit['amount']['value']) || ! is_numeric($unit['amount']['value']) || $unit['amount']['value'] <= 0) {
                throw new \InvalidArgumentException('Le montant de l\'unité d\'achat est invalide.');
            }
        }

        // Vérifier les URLs
        if (! isset($orderData['application_context']['cancel_url']) || ! isset($orderData['application_context']['return_url'])) {
            throw new \InvalidArgumentException('Les URLs de retour ou d\'annulation sont manquantes.');
        }
    }

    public function __construct(PayPalRestService $payPalRestService, CartService $cartService, OrderService $orderService)
    {
        $this->payPalRestService = $payPalRestService;
        $this->cartService       = $cartService;
        $this->orderService      = $orderService;
    }

    #[Route('/payment', name: 'app_payment')]
    public function createPayment(EntityManagerInterface $entityManager): Response
    {
        $cartSummary = $this->cartService->getCartSummary($entityManager);

        $this->container->get('logger')->info("🔍 Test log Symfony prod");

        $orderData = [
            'intent'              => 'CAPTURE',
            'purchase_units'      => [[
                'description' => 'Votre panier',
                'amount'      => [
                    'currency_code' => 'EUR',
                    'value'         => number_format($cartSummary['total'], 2, '.', ''),
                    'breakdown'     => [
                        'item_total' => [
                            'currency_code' => 'EUR',
                            'value'         => number_format($cartSummary['total'], 2, '.', ''), // Utilisation correcte
                        ],
                    ],
                ],
                'items'       => array_map(function ($item) {
                    return [
                        'name'        => $item['product']->getName(),
                        'quantity'    => $item['quantity'],
                        'unit_amount' => [
                            'currency_code' => 'EUR',
                            'value'         => number_format($item['unit_price'], 2, '.', ''), // Correction ici
                        ],
                    ];
                }, $cartSummary['items']),
            ]],
            'application_context' => [
                'cancel_url' => $this->generateUrl('cart_payment_cancel', [], 0),
                'return_url' => $this->generateUrl('cart_payment_success', [], 0),
            ],
        ];

        try {
            $response = $this->payPalRestService->createOrder($orderData);

            if (isset($response['id'])) {
                $orderId = $response['id'];

                // Vérification du statut de la commande avant redirection
                $status = $this->payPalRestService->getOrderStatus($orderId);
                if ($status !== 'CREATED') {
                    throw new \RuntimeException("Erreur : la commande PayPal n'est pas dans un état valide.");
                }

                // Trouver le lien de redirection vers PayPal
                foreach ($response['links'] as $link) {
                    if ($link['rel'] === 'approve') {
                        return $this->redirect($link['href']);
                    }
                }

                throw new \RuntimeException('Aucun lien de redirection PayPal trouvé.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur PayPal : ' . $e->getMessage());
        }

        return $this->redirectToRoute('cart_index');
    }

    //     try {
    //         $response = $this->payPalRestService->createOrder($orderData);

    //         if (isset($response['id'])) {
    //             $orderId = $response['id'];
    //             return $this->redirect("https://www.sandbox.paypal.com/checkoutnow?token=$orderId");
    //         }
    //     } catch (\Exception $e) {
    //         $this->addFlash('error', 'Erreur PayPal : ' . $e->getMessage());
    //     }

    //     return $this->redirectToRoute('cart_index');
    // }

    #[Route('/payment/success', name: 'cart_payment_success')]
    public function paymentSuccess(
        Request $request,
        EntityManagerInterface $entityManager,
        InvoiceGenerator $invoiceGenerator,
        MailerInterface $mailer
    ): Response {
        $orderId = $request->query->get('token');

        if (! $orderId) {
            $this->addFlash('error', 'Identifiant de commande introuvable.');
            return $this->redirectToRoute('cart_index');
        }

        try {
            $response = $this->payPalRestService->captureOrder($orderId);

            if ($response['status'] === 'COMPLETED') {
                // Récupération du récapitulatif du panier
                $cartSummary = $this->cartService->getCartSummary($entityManager);

                // Création de la commande
                $order = $this->orderService->createOrder(
                    $this->getUser(),
                    $cartSummary,
                    $orderId
                );

                // Mise à jour du statut de la commande
                $order->setStatus('COMPLETED');
                $entityManager->flush();

                // Nettoyer le panier après paiement réussi
                $this->cartService->cleanCart();

                $invoiceItems = array_map(function ($item) {
                    return [
                        'name'        => $item['product']->getName(),
                        'quantity'    => $item['quantity'],
                        'unit_price'  => $item['product']->getPrice(),
                        'total_price' => $item['quantity'] * $item['product']->getPrice(),
                    ];
                }, $cartSummary['items']);
                $invoicePath = $invoiceGenerator->generateInvoice([
                    'id'       => $order->getId(),
                    'date'     => new \DateTime(),
                    'customer' => [
                        'name'  => $this->getUser()->getPseudo(),
                        'email' => $this->getUser()->getEmail(),
                    ],
                    'items'    => $invoiceItems,
                    'total'    => $order->getTotalAmount(),
                ]);

                // Envoi de l'email
                $email = (new TemplatedEmail())
                    ->from('nathcrea.app@gmail.com')                      // L'expéditeur
                    ->to($this->getUser()->getEmail())                    // Le destinataire
                    ->subject('Confirmation de votre commande')           // Sujet de l'email
                    ->htmlTemplate('emails/order_confirmation.html.twig') // Le fichier Twig pour le contenu HTML
                    ->context([
                        'user'  => [
                            'firstName' => $this->getUser()->getFirstName(),
                            'lastName'  => $this->getUser()->getLastName(),
                            'address'   => $this->getUser()->getAddress(),
                        ],
                        'order' => [
                            'items'       => $cartSummary['items'],
                            'totalAmount' => $cartSummary['total'],
                        ],
                    ])
                    ->text('Merci pour votre commande. Veuillez trouver votre facture en pièce jointe.')
                    ->attachFromPath($invoicePath);

                $mailer->send($email);

                $this->addFlash('success', 'Paiement validé avec succès.');
                return $this->redirectToRoute('app_order_confirmation', ['orderId' => $order->getId()]);
            }

            throw new \Exception('Paiement non complété.');
        } catch (\Exception $e) {
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
