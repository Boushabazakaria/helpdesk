<?php

namespace App\Form;

use App\Entity\Ticket;
use App\Enum\TicketPriority;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TicketType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr'  => ['placeholder' => 'Décrivez votre problème en une ligne'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description détaillée',
                'attr'  => [
                    'placeholder' => 'Expliquez votre problème en détail...',
                    'rows'        => 6,
                ],
            ])
            // EnumType : Symfony génère automatiquement un <select> depuis l'enum PHP
            ->add('priority', EnumType::class, [
                'class'        => TicketPriority::class,
                'label'        => 'Priorité',
                'choice_label' => fn(TicketPriority $p) => $p->getLabel(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Ticket::class]);
    }
}
