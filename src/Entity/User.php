<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Utilisateur de la boutique.
 *
 * Un client (ROLE_USER) possède une adresse de livraison ; un administrateur (ROLE_ADMIN)
 * n'en a pas besoin. La connexion se fait avec le nom d'utilisateur, comme sur le wireframe.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['name'], message: 'Ce nom d\'utilisateur est déjà pris.')]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cette adresse e-mail.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Le nom d\'utilisateur est obligatoire.')]
    #[Assert\Length(min: 3, max: 50)]
    #[Assert\Regex(pattern: '/^[\w.\-]+$/u', message: 'Lettres, chiffres, points, tirets et soulignés uniquement.')]
    private string $name = '';

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L\'adresse e-mail est obligatoire.')]
    #[Assert\Email(message: 'L\'adresse e-mail n\'est pas valide.')]
    private string $email = '';

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    /** Mot de passe haché (jamais le mot de passe en clair). */
    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $deliveryAddress = null;

    /** Passe à true quand l'utilisateur a cliqué sur le lien reçu par e-mail. */
    #[ORM\Column]
    private bool $isVerified = false;

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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    /**
     * Identifiant utilisé par le composant Security : le nom d'utilisateur.
     */
    public function getUserIdentifier(): string
    {
        return $this->name;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // Tout utilisateur connecté a au minimum le rôle client.
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function isAdmin(): bool
    {
        return in_array('ROLE_ADMIN', $this->getRoles(), true);
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getDeliveryAddress(): ?string
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(?string $deliveryAddress): static
    {
        $this->deliveryAddress = null !== $deliveryAddress ? trim($deliveryAddress) : null;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    /**
     * Empêche de sérialiser le hash du mot de passe en clair dans la session.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }
}
