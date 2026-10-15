<?php

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

/**
 * Filtres d'affichage des prix au format français (29,90 €).
 * Ils évitent de dépendre de l'extension PHP intl.
 */
class PriceExtension
{
    /**
     * Prix exprimé en euros (ex. valeur DECIMAL "29.90").
     */
    #[AsTwigFilter('euros')]
    public function formatEuros(string|int|float|null $amount): string
    {
        return number_format((float) $amount, 2, ',', ' ').' €';
    }

    /**
     * Montant exprimé en centimes (ex. 2990).
     */
    #[AsTwigFilter('cents_to_euros')]
    public function formatCents(int $cents): string
    {
        return $this->formatEuros($cents / 100);
    }
}
