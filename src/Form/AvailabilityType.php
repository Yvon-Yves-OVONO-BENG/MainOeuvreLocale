<?php

namespace App\Form;

use App\Entity\StatusProfile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\DateType;

class AvailabilityType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('statusProfile', EntityType::class, [
                'class' => StatusProfile::class,
                'choice_label' => 'status', // <-- adapte : name/status/libelle
                'placeholder' => '-- Choisir un statut --',
                'required' => true,
                'mapped' => false,
            ])
            // Optionnel (si tu as un champ date dans ProfessionalProfile)
            ->add('nextAvailableAt', DateType::class, [
                'widget' => 'single_text',
                'required' => false,
                'mapped' => false,
                'label' => 'Prochaine disponibilité',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
