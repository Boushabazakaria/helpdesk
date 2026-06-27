<?php

namespace App\Form;

use App\Entity\TicketResponse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TicketResponseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('content', TextareaType::class, [
            'label' => false, // le label est géré dans le template
            'attr'  => [
                'placeholder' => 'Écrivez votre réponse...',
                'rows'        => 4,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => TicketResponse::class]);
    }
}
