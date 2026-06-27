<?php

namespace App\Form;

use App\Entity\User;
use App\Enum\TicketPriority;
use App\Enum\TicketStatus;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TicketFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('status', EnumType::class, [
                'class'        => TicketStatus::class,
                'label'        => 'Statut',
                'required'     => false,
                'placeholder'  => 'Tous les statuts',
                'choice_label' => fn(TicketStatus $s) => $s->getLabel(),
            ])
            ->add('priority', EnumType::class, [
                'class'        => TicketPriority::class,
                'label'        => 'Priorité',
                'required'     => false,
                'placeholder'  => 'Toutes les priorités',
                'choice_label' => fn(TicketPriority $p) => $p->getLabel(),
            ]);

        // Filtre par agent — uniquement visible pour agents/admins
        if ($options['show_agent_filter']) {
            $builder->add('agent', EntityType::class, [
                'class'        => User::class,
                'label'        => 'Agent',
                'required'     => false,
                'placeholder'  => 'Tous les agents',
                'choice_label' => fn(User $u) => $u->getFullName(),
                'query_builder' => fn(UserRepository $ur) => $ur->createQueryBuilder('u')
                    ->where('u.roles LIKE :agent OR u.roles LIKE :admin')
                    ->setParameter('agent', '%ROLE_AGENT%')
                    ->setParameter('admin', '%ROLE_ADMIN%')
                    ->orderBy('u.lastName', 'ASC'),
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method'            => 'GET',   // filtres dans l'URL, pas en POST
            'csrf_protection'   => false,   // pas de CSRF sur les filtres GET
            'show_agent_filter' => false,
        ]);
    }
}
