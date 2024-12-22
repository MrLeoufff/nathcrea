<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Review;
use App\Form\ReviewType;
use App\Repository\CategoryRepository;
use App\Repository\ReviewRepository;
use App\Service\NoXSS;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    private NoXSS $noXSS;

    public function __construct(NoXSS $noXSS)
    {
        $this->noXSS = $noXSS;
    }

    #[Route('/', name: 'app_home')]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        ReviewRepository $reviewRepository
    ): Response {
        $categories = $entityManager->getRepository(Category::class)->findAll();
        $approvedReviews = $reviewRepository->findBy(['isApproved' => true]);

        foreach ($approvedReviews as $review) {
            $review->setContent($this->noXSS->nettoyage($review->getContent()));
        }

        $reviewForm = null;

        // Permettre aux utilisateurs connectés de soumettre un avis
        if ($this->getUser()) {
            $review = new Review();
            $review->setUser($this->getUser());
            $review->setCreatedAt(new \DateTimeImmutable());
            $review->setApproved(false);

            $reviewForm = $this->createForm(ReviewType::class, $review);
            $reviewForm->handleRequest($request);

            // Initialisation de la variable $acceptPseudo
            $acceptPseudo = false;

            if ($reviewForm->isSubmitted()) {
                $acceptPseudo = $request->request->get('accept_pseudo') === '1';

                if (!$acceptPseudo) {
                    $this->addFlash('error', 'Vous devez accepter que votre pseudo soit affiché pour soumettre un avis.');
                } elseif ($reviewForm->isValid()) {
                    $review->setContent($this->noXSS->nettoyage($review->getContent()));

                    $entityManager->persist($review);
                    $entityManager->flush();

                    $this->addFlash('success', 'Votre avis a été soumis et est en attente de validation.');

                    return $this->redirectToRoute('app_home');
                } else {
                    $this->addFlash('error', 'Erreur lors de la soumission de votre avis.');
                }
            }
        }

        return $this->render('home/index.html.twig', [
            'categories' => $categories,
            'reviews' => $approvedReviews,
            'reviewForm' => $reviewForm ? $reviewForm->createView() : null,
        ]);
    }

    #[Route('/category/{id}', name: 'app_category_products')]
    public function categoryProducts(Category $category): Response
    {
        $products = $category->getProducts();

        return $this->render('home/category_products.html.twig', [
            'category' => $category,
            'products' => $products,
        ]);
    }

    #[Route('/category', name: 'app_category')]
    public function categories(CategoryRepository $categoryRepository): Response
    {
        $categories = $categoryRepository->findAll();

        return $this->render('home/category.html.twig', [
            'categories' => $categories,
        ]);
    }

    #[Route('/product/{id}', name: 'app_product_detail')]
    public function productDetail(Product $product): Response
    {
        return $this->render('home/product_detail.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/order/{id}', name: 'app_order_product')]
    public function orderProduct(int $id, Product $product, EntityManagerInterface $entityManager, Request $request): Response
    {
        if (!$this->getUser()) {
            $this->addFlash('error', 'Vous devez être connecté pour passer une commande.');
            return $this->redirectToRoute('app_login');
        }

        // Ajouter le produit à la commande de l'utilisateur
        // Logique simplifiée : ajouter au panier ou créer une commande (selon votre implémentation)
        $this->addFlash('success', "Le produit {$product->getName()} a été ajouté à votre commande !");

        // Rediriger vers la page des produits ou du panier
        return $this->redirectToRoute('cart_add', ['id' => $id]);
    }
}
