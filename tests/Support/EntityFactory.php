<?php

namespace App\Tests\Support;

use App\Entity\Product;
use App\Entity\User;
use App\Enum\Size;

/**
 * Fabrique d'entités pour les tests unitaires (sans base de données).
 */
final class EntityFactory
{
    public static function product(int $id, string $name, string $price, int $stockPerSize = 2): Product
    {
        $product = (new Product())->setName($name)->setPrice($price)->setImage($id.'.jpeg');
        foreach (Size::cases() as $size) {
            $product->setStock($size, $stockPerSize);
        }

        return self::withId($product, $id);
    }

    public static function customer(int $id = 1): User
    {
        $user = (new User())
            ->setName('johndoe')
            ->setEmail('johndoe@stubborn.test')
            ->setDeliveryAddress('8 rue du bac 54100 Nancy')
            ->setIsVerified(true);

        return self::withId($user, $id);
    }

    /**
     * Simule l'identifiant attribué par la base de données.
     *
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    public static function withId(object $entity, int $id): object
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);

        return $entity;
    }
}
