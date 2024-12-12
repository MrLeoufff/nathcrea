<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

class InvoiceGenerator
{
    private Environment $twig;
    private string $projectDir;

    public function __construct(Environment $twig, KernelInterface $kernel)
    {
        $this->twig = $twig;
        $this->projectDir = $kernel->getProjectDir();
    }

    public function generateInvoice(array $orderDetails): string
    {
        // Configure Dompdf
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);

        // Générer le contenu HTML pour le PDF
        $html = $this->twig->render('invoice/invoice.html.twig', [
            'order' => $orderDetails,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Enregistrer le PDF dans un fichier temporaire
        $output = $dompdf->output();
        $filePath = $this->projectDir . '/public/uploads/invoices/invoice_' . $orderDetails['id'] . '.pdf';
        file_put_contents($filePath, $output);

        return $filePath;
    }
}
