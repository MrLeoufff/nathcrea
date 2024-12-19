<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create();

        // Création de l'Administrateur
        $admin = new User();
        $admin->setEmail('nathcrea.app@gmail.com');
        $admin->setPseudo('Nath');
        $admin->setFirstName('Nathalie');
        $admin->setLastName('Cheron');
        $admin->setAddress('Corse');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setVerified(1);
        $admin->setCreatedAt(new \DateTimeImmutable());
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Admin#123'));
        $manager->persist($admin);

        // // Création de 10 clients
        // for ($i = 1; $i <= 3; $i++) {
        //     $client = new User();
        //     $client->setEmail("client$i@example.com");
        //     $client->setPseudo("client$i");
        //     $client->setRoles(['ROLE_USER']);
        //     $client->setCreatedAt(new \DateTimeImmutable());
        //     $client->setVerified(1);
        //     $client->setPassword($this->passwordHasher->hashPassword($client, 'password'));
        //     $manager->persist($client);
        // }

        // // Création des 5 catégories
        // $categories = [];
        // for ($i = 1; $i <= 5; $i++) {
        //     $category = new Category();
        //     $category->setName("Category $i");
        //     $category->setDescription($faker->sentence());
        //     $category->setCreatedAt(new \DateTimeImmutable());
        //     $category->setImage($faker->imageUrl(640, 480, 'categories', true, "Category $i"));
        //     $manager->persist($category);
        //     $categories[] = $category;
        // }

        // // Création de 10 articles par catégorie
        // foreach ($categories as $category) {
        //     for ($i = 1; $i <= 10; $i++) {
        //         $product = new Product();
        //         $product->setName("Product $i of {$category->getName()}");
        //         $product->setDescription($faker->paragraph());
        //         $product->setCreatedAt(new \DateTimeImmutable());
        //         $product->setPrice($faker->randomFloat(2, 5, 100)); // Prix entre 5 et 100
        //         $product->setStock($faker->numberBetween(1, 50)); // Stock entre 1 et 50
        //         $product->setCategory($category);
        //         $product->setImage($faker->imageUrl(640, 480, 'products', true, "Product $i"));
        //         $manager->persist($product);
        //     }
        // }

        // Flush pour sauvegarder toutes les entités
        $manager->flush();
    }
}
