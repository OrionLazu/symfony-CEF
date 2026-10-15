<?php

namespace App\Tests\Model;

use App\Model\PriceRange;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * Tests des fourchettes de prix du filtre de la boutique.
 */
#[TestDox('Filtre par prix')]
class PriceRangeTest extends TestCase
{
    #[TestDox('Les trois fourchettes du cahier des charges sont proposées')]
    public function testRangesFromSpecification(): void
    {
        self::assertSame(['10-29', '29-35', '35-50'], array_keys(PriceRange::all()));
    }

    #[TestDox('Chaque sweat-shirt de la collection tombe dans une seule fourchette')]
    public function testEachPriceBelongsToOneRangeAtMost(): void
    {
        // Prix de la collection du brief (BornInUsa à 59,90 € n'entre dans aucune fourchette).
        $expected = ['28.50' => '10-29', '29.90' => '29-35', '32.00' => '29-35', '34.50' => '29-35', '42.20' => '35-50', '45.00' => '35-50', '59.90' => null];

        foreach ($expected as $price => $rangeKey) {
            $matches = array_keys(array_filter(PriceRange::all(), static fn (PriceRange $range) => $range->contains((float) $price)));
            self::assertSame(null === $rangeKey ? [] : [$rangeKey], $matches, 'Prix '.$price);
        }
    }

    #[TestDox('Les bornes sont gérées sans chevauchement (29 € et 35 €)')]
    public function testBoundaries(): void
    {
        self::assertFalse(PriceRange::fromKey('10-29')->contains(29.0));
        self::assertTrue(PriceRange::fromKey('29-35')->contains(29.0));
        self::assertFalse(PriceRange::fromKey('29-35')->contains(35.0));
        self::assertTrue(PriceRange::fromKey('35-50')->contains(50.0));
    }

    #[TestDox('Une valeur inconnue dans l\'URL désactive le filtre')]
    public function testUnknownKeyGivesNoFilter(): void
    {
        self::assertNull(PriceRange::fromKey('nimportequoi'));
        self::assertNull(PriceRange::fromKey(null));
    }
}
