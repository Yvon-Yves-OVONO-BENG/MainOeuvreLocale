<?php

namespace App\Form;

use App\Entity\SupportTicket;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SupportTicketType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', TextType::class, [
                'label' => 'Sujet',
            ])
            ->add('customerName', TextType::class, [
                'label' => 'Nom du client',
            ])
            ->add('customerEmail', EmailType::class, [
                'label' => 'Email du client',
            ])
            ->add('channel', ChoiceType::class, [
                'label' => 'Canal',
                'choices' => array_combine(SupportTicket::CHANNELS, SupportTicket::CHANNELS),
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Priorité',
                'choices' => array_combine(SupportTicket::PRIORITIES, SupportTicket::PRIORITIES),
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => array_combine(SupportTicket::STATUSES, SupportTicket::STATUSES),
            ])
            ->add('ownerName', TextType::class, [
                'label' => 'Assigné à',
                'required' => false,
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message / Description',
                'required' => false,
                'attr' => [
                    'rows' => 8,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SupportTicket::class,
        ]);
    }
}