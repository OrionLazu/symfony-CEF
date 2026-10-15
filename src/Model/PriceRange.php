<?php

namespace App\Model;

/**
 * Fourchette de prix proposée dans le filtre de la boutique.
 */
final class PriceRange
{
    private function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly float $min,
        public readonly float $max,
        public readonly bool $includeMax,
    ) {
    }

    /**
     * Fourchettes définies par le cahier des charges : 10 à 29 €, 29 à 35 €, 35 à 50 €.
     * Les bornes basses sont incluses et les bornes hautes exclues (sauf 50 €),
     * pour qu'un prix ne tombe jamais dans deux fourchettes à la fois.
     *
     * @return array<string, self>
     */
    public static function all(): array
    {
        return [
            '10-29' => new self('10-29', '10 à 29 €', 10, 29, false),
            '29-35' => new self('29-35', '29 à 35 €', 29, 35, false),
            '35-50' => new self('35-50', '35 à 50 €', 35, 50, true),
        ];
    }

    /**
     * Retourne la fourchette correspondant à la clé reçue dans l'URL, ou null si elle est inconnue.
     */
    public static function fromKey(?string $key): ?self
    {
        return self::all()[(string) $key] ?? null;
    }

    /**
     * Indique si un prix appartient à cette fourchette.
     */
    public function contains(float $price): bool
    {
        return $price >= $this->min && ($this->includeMax ? $price <= $this->max : $price < $this->max);
    }
}
