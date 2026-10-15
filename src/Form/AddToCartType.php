<?php

namespace App\Form;

use App\Entity\Product;
use App\Enum\Size;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Choix de la taille sur la fiche d'un sweat-shirt.
 * Les tailles en rupture de stock sont affichées mais désactivées.
 */
class AddToCartType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Product $product */
        $product = $options['product'];

        $builder->add('size', EnumType::class, [
            'class' => Size::class,
            'label' => 'Taille',
            'expanded' => false,
            'multiple' => false,
            'placeholder' => false,
            'choice_label' => static fn (Size $size) => $size->value,
            'choice_attr' => static fn (Size $size) => $product->getStock($size) > 0 ? [] : ['disabled' => 'disabled'],
            'constraints' => [new NotNull(message: 'Choisissez une taille.')],
            'attr' => ['size' => count(Size::cases())],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('product');
        $resolver->setAllowedTypes('product', Product::class);
    }
}
