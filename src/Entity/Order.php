<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Commande passée par un client.
 *
 * Elle est créée au statut "en attente" quand le client clique sur « Finaliser ma commande »,
 * puis passe à "payée" quand Stripe confirme le paiement.
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELED = 'canceled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $customer;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    /** Montant total en centimes. */
    #[ORM\Column]
    private int $totalInCents = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $deliveryAddress = null;

    /** Identifiant de la session Stripe Checkout associée. */
    #[ORM\Column(length: 255, nullable: true, unique: true)]
    private ?string $stripeSessionId = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist'], orphanRemoval: true)]
    private Collection $items;

    public function __construct(User $customer)
    {
        $this->customer = $customer;
        $this->deliveryAddress = $customer->getDeliveryAddress();
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): User
    {
        return $this->customer;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isPaid(): bool
    {
        return self::STATUS_PAID === $this->status;
    }

    public function isPending(): bool
    {
        return self::STATUS_PENDING === $this->status;
    }

    /**
     * Passe la commande au statut « payée » et horodate le paiement.
     */
    public function markAsPaid(): static
    {
        $this->status = self::STATUS_PAID;
        $this->paidAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Passe la commande au statut « annulée » (paiement abandonné).
     */
    public function markAsCanceled(): static
    {
        $this->status = self::STATUS_CANCELED;

        return $this;
    }

    public function getTotalInCents(): int
    {
        return $this->totalInCents;
    }

    public function getDeliveryAddress(): ?string
    {
        return $this->deliveryAddress;
    }

    public function getStripeSessionId(): ?string
    {
        return $this->stripeSessionId;
    }

    public function setStripeSessionId(?string $stripeSessionId): static
    {
        $this->stripeSessionId = $stripeSessionId;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * Ajoute une ligne et recalcule le total de la commande.
     */
    public function addItem(OrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
            $this->totalInCents += $item->getSubtotalInCents();
        }

        return $this;
    }
}
