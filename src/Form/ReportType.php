<?php

namespace App\Form;

use App\Entity\Report;
use App\Entity\CategorieReport;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('categorie', EntityType::class, [
                'class' => CategorieReport::class,
                'choice_label' => 'label',
                'label' => 'Catégorie',
                'placeholder' => 'Choisir une catégorie',
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'Expliquez brièvement',
                'attr' => [
                    'rows' => 5,
                    'placeholder' => "Décrivez le problème (spam, arnaque, usurpation, harcèlement...)",
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Report::class,
        ]);
    }
}