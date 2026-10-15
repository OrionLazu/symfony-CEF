<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Formulaire d'inscription d'un client (champs du wireframe « S'inscrire »).
 */
class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom utilisateur :',
                'empty_data' => '',
                'attr' => ['autocomplete' => 'username', 'placeholder' => 'johndoe'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse mail :',
                'empty_data' => '',
                'attr' => ['autocomplete' => 'email', 'placeholder' => 'johndoe@gmail.com'],
            ])
            ->add('deliveryAddress', TextType::class, [
                'label' => 'Adresse de livraison',
                'attr' => ['autocomplete' => 'street-address', 'placeholder' => '8 rue du bac 54100 Nancy'],
                'constraints' => [new NotBlank(message: 'L\'adresse de livraison est obligatoire.')],
            ])
            // Le mot de passe n'est pas lié à l'entité : il est haché dans le contrôleur.
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'first_options' => ['label' => 'Mot de passe', 'attr' => ['autocomplete' => 'new-password']],
                'second_options' => ['label' => 'Confirmer le mot de passe :', 'attr' => ['autocomplete' => 'new-password']],
                'constraints' => [
                    new NotBlank(message: 'Le mot de passe est obligatoire.'),
                    new Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'),
                    new Regex(pattern: '/^(?=.*[a-zA-Z])(?=.*\d).+$/', message: 'Le mot de passe doit contenir au moins une lettre et un chiffre.'),
                    new NotCompromisedPassword(message: 'Ce mot de passe figure dans une fuite de données connue, choisissez-en un autre.', skipOnError: true),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
