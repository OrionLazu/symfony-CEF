<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Saisie du nouveau mot de passe après clic sur le lien de réinitialisation.
 */
class ChangePasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('plainPassword', RepeatedType::class, [
            'type' => PasswordType::class,
            'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
            'first_options' => ['label' => 'Nouveau mot de passe', 'attr' => ['autocomplete' => 'new-password']],
            'second_options' => ['label' => 'Confirmer le mot de passe :', 'attr' => ['autocomplete' => 'new-password']],
            'constraints' => [
                new NotBlank(message: 'Le mot de passe est obligatoire.'),
                new Length(min: 8, max: 4096, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'),
                new Regex(pattern: '/^(?=.*[a-zA-Z])(?=.*\d).+$/', message: 'Le mot de passe doit contenir au moins une lettre et un chiffre.'),
                new NotCompromisedPassword(message: 'Ce mot de passe figure dans une fuite de données connue, choisissez-en un autre.', skipOnError: true),
            ],
        ]);
    }
}
