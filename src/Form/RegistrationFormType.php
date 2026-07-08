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

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'attr'  => ['placeholder' => 'Votre prénom'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'attr'  => ['placeholder' => 'Votre nom'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr'  => ['placeholder' => 'votre@email.com'],
            ])
            // RepeatedType : deux champs "mot de passe" qui doivent correspondre
            ->add('plainPassword', RepeatedType::class, [
                'type'            => PasswordType::class,
                'mapped'          => false, // pas mappé sur User::$password (c'est le hash)
                'first_options'   => ['label' => 'Mot de passe', 'attr' => ['placeholder' => '••••••••']],
                'second_options'  => ['label' => 'Confirmer le mot de passe', 'attr' => ['placeholder' => '••••••••']],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'constraints'     => [
                    new NotBlank(message: 'Le mot de passe est obligatoire.'),
                    new Length(
                        min: 8,
                        minMessage: 'Le mot de passe doit faire au moins {{ limit }} caractères.',
                    ),
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
