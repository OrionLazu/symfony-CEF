<?php

namespace App\Enum;

/**
 * Tailles proposées pour les sweat-shirts Stubborn.
 *
 * L'ordre de déclaration est celui affiché dans les formulaires (du plus petit au plus grand).
 */
enum Size: string
{
    case XS = 'XS';
    case S = 'S';
    case M = 'M';
    case L = 'L';
    case XL = 'XL';
}
