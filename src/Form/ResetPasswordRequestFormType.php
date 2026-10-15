<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Demande de lien de réinitialisation (« Mot de passe oublié »).
 */
class ResetPasswordRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('email', EmailType::class, [
            'label' => 'Adresse mail :',
            'attr' => ['autocomplete' => 'email'],
            'constraints' => [
                new NotBlank(message: 'Saisissez votre adresse e-mail.'),
                new Email(message: 'L\'adresse e-mail n\'est pas valide.'),
            ],
        ]);
    }
}
