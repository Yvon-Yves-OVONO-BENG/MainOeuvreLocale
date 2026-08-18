<?php

namespace App\Form;

use App\Entity\KycRule;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class KycRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ruleKey', TextType::class, [
                'label' => 'Clé technique',
            ])
            ->add('label', TextType::class, [
                'label' => 'Libellé',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['rows' => 5],
            ])
            ->add('groupName', ChoiceType::class, [
                'label' => 'Groupe',
                'choices' => [
                    'Talents' => 'Talents',
                    'Particuliers' => 'Particuliers',
                    'Compagnies' => 'Compagnies',
                    'Offres' => 'Offres',
                    'Risque' => 'Risque',
                    'Général' => 'Général',
                ],
            ])
            ->add('level', ChoiceType::class, [
                'label' => 'Niveau',
                'choices' => [
                    'Low' => 'low',
                    'Medium' => 'medium',
                    'High' => 'high',
                ],
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'Règle active',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => KycRule::class,
        ]);
    }
}