<?php

namespace App\Entity;

use App\Enum\Size;
use App\Repository\ProductRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Sweat-shirt vendu sur la boutique.
 *
 * Le prix est identique pour toutes les tailles ; le stock est suivi taille par taille
 * (une colonne par taille, ce qui correspond au formulaire du back-office).
 */
#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 100)]
    private string $name = '';

    /** Prix TTC en euros, stocké en DECIMAL pour éviter les erreurs d'arrondi des flottants. */
    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    #[Assert\NotBlank(message: 'Le prix est obligatoire.')]
    #[Assert\Positive(message: 'Le prix doit être supérieur à 0.')]
    #[Assert\LessThan(10000)]
    private string $price = '0.00';

    /** Nom du fichier image situé dans public/images/products. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /** Sweat-shirt mis en avant sur la page d'accueil. */
    #[ORM\Column]
    private bool $featured = false;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Le stock ne peut pas être négatif.')]
    private int $stockXs = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Le stock ne peut pas être négatif.')]
    private int $stockS = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Le stock ne peut pas être négatif.')]
    private int $stockM = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Le stock ne peut pas être négatif.')]
    private int $stockL = 0;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'Le stock ne peut pas être négatif.')]
    private int $stockXl = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    /**
     * Accepte un nombre ou une chaîne ("29,90" ou "29.90") et la normalise au format DECIMAL.
     */
    public function setPrice(string|int|float|null $price): static
    {
        $normalized = str_replace(',', '.', trim((string) $price));
        $this->price = is_numeric($normalized) ? number_format((float) $normalized, 2, '.', '') : '0.00';

        return $this;
    }

    /**
     * Prix en centimes : toutes les opérations de calcul (panier, Stripe) travaillent en entiers.
     */
    public function getPriceInCents(): int
    {
        return (int) round(((float) $this->price) * 100);
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): static
    {
        $this->featured = $featured;

        return $this;
    }

    public function getStock(Size $size): int
    {
        return match ($size) {
            Size::XS => $this->stockXs,
            Size::S => $this->stockS,
            Size::M => $this->stockM,
            Size::L => $this->stockL,
            Size::XL => $this->stockXl,
        };
    }

    public function setStock(Size $size, int $quantity): static
    {
        if ($quantity < 0) {
            throw new \InvalidArgumentException('Le stock ne peut pas être négatif.');
        }

        match ($size) {
            Size::XS => $this->stockXs = $quantity,
            Size::S => $this->stockS = $quantity,
            Size::M => $this->stockM = $quantity,
            Size::L => $this->stockL = $quantity,
            Size::XL => $this->stockXl = $quantity,
        };

        return $this;
    }

    public function getTotalStock(): int
    {
        return array_sum(array_map(fn (Size $size) => $this->getStock($size), Size::cases()));
    }

    // Accesseurs par taille utilisés par le formulaire du back-office.

    public function getStockXs(): int
    {
        return $this->stockXs;
    }

    public function setStockXs(?int $stock): static
    {
        $this->stockXs = $stock ?? 0;

        return $this;
    }

    public function getStockS(): int
    {
        return $this->stockS;
    }

    public function setStockS(?int $stock): static
    {
        $this->stockS = $stock ?? 0;

        return $this;
    }

    public function getStockM(): int
    {
        return $this->stockM;
    }

    public function setStockM(?int $stock): static
    {
        $this->stockM = $stock ?? 0;

        return $this;
    }

    public function getStockL(): int
    {
        return $this->stockL;
    }

    public function setStockL(?int $stock): static
    {
        $this->stockL = $stock ?? 0;

        return $this;
    }

    public function getStockXl(): int
    {
        return $this->stockXl;
    }

    public function setStockXl(?int $stock): static
    {
        $this->stockXl = $stock ?? 0;

        return $this;
    }
}
