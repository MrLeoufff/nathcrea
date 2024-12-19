<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('admin')]
class ProductController extends AbstractController
{
    #[Route('/products', name: 'app_products')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $products = $entityManager->getRepository(Product::class)->findAll();

        return $this->render('product/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/products/new', name: 'app_product_new')]
    public function new (Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = new Product();
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de la date de création
            $product->setCreatedAt(new \DateTimeImmutable());

            // Gestion de l'image
            if ($this->handleImageUpload($form, $product)) {
                $entityManager->persist($product);
                $entityManager->flush();

                $this->addFlash('success', 'Produit ajouté avec succès.');
                return $this->redirectToRoute('app_products');
            }
        }

        return $this->render('product/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/products/{id}/edit', name: 'app_product_edit')]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Sauvegarder l'image actuelle
            $oldImage = $product->getImage();

            // Upload de la nouvelle image
            if ($this->handleImageUpload($form, $product)) {
                $this->deleteOldImage($oldImage);
            }

            $entityManager->flush();

            $this->addFlash('success', 'Produit modifié avec succès.');
            return $this->redirectToRoute('app_products');
        }

        return $this->render('product/edit.html.twig', [
            'form' => $form->createView(),
            'product' => $product,
        ]);
    }

    #[Route('/products/{id}/delete', name: 'app_product_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $product->getId(), $request->request->get('_token'))) {
            $this->deleteOldImage($product->getImage());

            $entityManager->remove($product);
            $entityManager->flush();

            $this->addFlash('success', 'Produit supprimé avec succès.');
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('app_products');
    }

    private function handleImageUpload($form, Product $product): bool
    {
        $imageFile = $form->get('imageFile')->getData();
        if ($imageFile) {
            // Valider les extensions de fichiers
            if (!in_array($imageFile->guessExtension(), ['jpg', 'jpeg', 'png'])) {
                $this->addFlash('error', 'Seules les images au format JPG ou PNG sont acceptées.');
                return false;
            }

            try {
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                $imageFile->move($this->getParameter('images_directory'), $newFilename);
                $product->setImage($newFilename);
                return true;
            } catch (FileException $e) {
                $this->addFlash('error', 'Erreur lors de l\'upload de l\'image : ' . $e->getMessage());
            }
        }

        return false;
    }

    private function deleteOldImage(?string $filename): void
    {
        if ($filename) {
            $imagePath = $this->getParameter('images_directory') . '/' . $filename;
            if (file_exists($imagePath)) {
                try {
                    unlink($imagePath);
                } catch (\Exception $e) {
                    $this->addFlash('warning', 'Erreur lors de la suppression de l\'image : ' . $e->getMessage());
                }
            }
        }
    }
}
