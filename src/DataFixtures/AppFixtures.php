<?php

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\User;
use App\Enum\Size;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Données de démonstration : la collection fournie avec le brief et deux comptes de test.
 *
 * Chargement : php bin/console doctrine:fixtures:load
 */
class AppFixtures extends Fixture
{
    /** Comptes de test (environnement de développement uniquement). */
    public const ADMIN_NAME = 'admin';
    public const ADMIN_PASSWORD = 'StubbornAdmin2026';
    public const CLIENT_NAME = 'johndoe';
    public const CLIENT_PASSWORD = 'StubbornClient2026';

    /**
     * Collection du brief : nom, prix, image fournie, mise en avant (**).
     *
     * @var list<array{string, string, string, bool}>
     */
    private const PRODUCTS = [
        ['Blackbelt', '29.90', '1.jpeg', true],
        ['BlueBelt', '29.90', '2.jpeg', false],
        ['Street', '34.50', '3.jpeg', false],
        ['Pokeball', '45.00', '4.jpeg', true],
        ['PinkLady', '29.90', '5.jpeg', false],
        ['Snow', '32.00', '6.jpeg', false],
        ['Greyback', '28.50', '7.jpeg', false],
        ['BlueCloud', '45.00', '8.jpeg', false],
        ['BornInUsa', '59.90', '9.jpeg', true],
        ['GreenSchool', '42.20', '10.jpeg', false],
    ];

    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::PRODUCTS as $index => [$name, $price, $image, $featured]) {
            $product = (new Product())
                ->setName($name)
                ->setPrice($price)
                ->setImage($image)
                ->setFeatured($featured);

            // Toutes les tailles sont disponibles, avec au moins 2 exemplaires chacune.
            foreach (Size::cases() as $sizeIndex => $size) {
                $product->setStock($size, 2 + ($index + $sizeIndex) % 4);
            }

            $manager->persist($product);
        }

        $admin = (new User())
            ->setName(self::ADMIN_NAME)
            ->setEmail('admin@stubborn.test')
            ->setRoles(['ROLE_ADMIN'])
            ->setIsVerified(true);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, self::ADMIN_PASSWORD));
        $manager->persist($admin);

        $client = (new User())
            ->setName(self::CLIENT_NAME)
            ->setEmail('johndoe@stubborn.test')
            ->setDeliveryAddress('8 rue du bac 54100 Nancy')
            ->setRoles(['ROLE_USER'])
            ->setIsVerified(true);
        $client->setPassword($this->passwordHasher->hashPassword($client, self::CLIENT_PASSWORD));
        $manager->persist($client);

        $manager->flush();
    }
}
