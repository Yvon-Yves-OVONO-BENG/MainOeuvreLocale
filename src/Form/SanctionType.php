<?php
// src/Form/SanctionType.php

namespace App\Form;

use App\Entity\Sanction;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SanctionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => static function (User $user): string {
                    return $user->getEmail() ?? ('User #' . $user->getId());
                },
                'label' => 'Utilisateur concerné',
                'placeholder' => 'Sélectionner un utilisateur',
                'required' => true,
            ])

            ->add('type', ChoiceType::class, [
                'label' => 'Type de sanction',
                'choices' => [
                    'Avertissement' => Sanction::TYPE_WARNING,
                    'Suspension' => Sanction::TYPE_SUSPEND,
                    'Bannissement' => Sanction::TYPE_BAN,
                    'Restriction' => Sanction::TYPE_RESTRICT,
                ],
                'required' => true,
            ])

            ->add('reason', TextareaType::class, [
                'label' => 'Motif',
                'required' => true,
                'attr' => [
                    'rows' => 5,
                ],
            ])

            ->add('message', TextareaType::class, [
                'label' => 'Message envoyé à l’utilisateur',
                'required' => false,
                'attr' => [
                    'rows' => 8,
                ],
            ])

            ->add('adminNote', TextareaType::class, [
                'label' => 'Note interne admin',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                ],
            ])

            ->add('startsAt', DateTimeType::class, [
                'label' => 'Début d’effet',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])

            ->add('endsAt', DateTimeType::class, [
                'label' => 'Fin d’effet',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Sanction::class,
        ]);
    }
}