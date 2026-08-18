<?php

namespace App\Form;

use App\Entity\TypeCompte;
use App\Repository\TypeCompteRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChooseTypeCompteFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('typeCompte', EntityType::class, [
            'class' => TypeCompte::class,
            'choice_label' => 'code',
            'placeholder' => 'Choisissez votre type de compte',
            'mapped' => false,
            'required' => true,
            'query_builder' => function (TypeCompteRepository $typeCompteRepository) {
                return $typeCompteRepository->createQueryBuilder('tc')
                    ->where('tc.code NOT IN (:excludedCodes)')
                    ->setParameter('excludedCodes', [
                        'MODERATEUR',
                        'ADMIN',
                        'SUPER_ADMIN',
                    ])
                    ->orderBy('tc.code', 'ASC');
            },
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}