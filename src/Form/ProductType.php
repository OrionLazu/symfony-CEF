<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * Formulaire du back-office : une ligne par sweat-shirt (ajout ou modification).
 */
class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('price', NumberType::class, [
                'label' => 'Prix',
                'scale' => 2,
                'input' => 'string',
                'html5' => true,
                'attr' => ['min' => '0.01', 'step' => '0.01'],
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Image',
                'mapped' => false,
                'required' => $options['image_required'],
                'constraints' => array_filter([
                    $options['image_required'] ? new NotNull(message: 'Choisissez une image.') : null,
                    new File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Formats acceptés : JPEG, PNG ou WebP.',
                    ),
                ]),
            ])
            ->add('featured', CheckboxType::class, [
                'label' => 'Mis en avant',
                'required' => false,
            ]);

        foreach (['stockXs' => 'Stock XS', 'stockS' => 'Stock S', 'stockM' => 'Stock M', 'stockL' => 'Stock L', 'stockXl' => 'Stock XL'] as $field => $label) {
            $builder->add($field, IntegerType::class, [
                'label' => $label,
                'attr' => ['min' => 0],
                'empty_data' => '0',
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
            'image_required' => false,
        ]);
        $resolver->setAllowedTypes('image_required', 'bool');
    }
}
