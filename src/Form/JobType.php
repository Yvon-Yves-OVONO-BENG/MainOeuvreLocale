<?php

namespace App\Form;

use App\Entity\Job;
use App\Entity\Profession;
use App\Entity\StatusJob;
use App\Entity\TypeJob;
use App\Repository\ProfessionRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class JobType extends AbstractType
{
    public function __construct(
        private readonly ProfessionRepository $professionRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du job',
                'attr' => [
                    'placeholder' => 'Ex: Développeur Symfony Senior',
                    'minlength' => 3,
                    'maxlength' => 255,
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Saisissez un intitulé de poste.'),
                    new Assert\Length(min: 3, max: 255),
                    new Assert\Regex(
                        pattern: "/^(?!\s*\d+\s*$)[\p{L}\p{M}0-9 .,'’+\-\/()]+$/u",
                        message: 'Utilisez un intitulé de métier ou de poste. Les chiffres seuls ne sont pas acceptés.'
                    ),
                ],
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'attr' => ['placeholder' => 'Ex: Yaoundé'],
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('typeJob', EntityType::class, [
                'label' => 'Type de job',
                'class' => TypeJob::class,
                'choice_label' => 'typeJob',
                'placeholder' => 'Choisir...',
                'required' => true,
                'constraints' => [
                    new Assert\NotNull(message: 'Choisissez un type de job.'),
                ],
            ])
            ->add('profession', EntityType::class, [
                'label' => 'Profession / Catégorie',
                'class' => Profession::class,
                'choice_label' => 'profession',
                'placeholder' => 'Choisir...',
                'required' => true,
                'choices' => $this->professionRepository->findUniqueNonEmptyOrdered(
                    null,
                    $options['data'] instanceof Job ? $options['data']->getProfession()?->getId() : null
                ),
                'constraints' => [
                    new Assert\NotNull(message: 'Choisissez une profession ou une catégorie.'),
                ],
            ])
            ->add('experience', ChoiceType::class, [
                'label' => 'Expérience',
                'choices' => [
                    'Débutant (0-1 an)' => '0-1',
                    'Intermédiaire (2-4 ans)' => '2-4',
                    'Confirmé (5+ ans)' => '5+',
                ],
                'placeholder' => 'Choisir...',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('salaireMin', IntegerType::class, [
                'label' => 'Salaire minimum',
                'attr' => ['placeholder' => 'Ex: 150000', 'min' => 0],
                'required' => true,
                'constraints' => [
                    new Assert\NotNull(),
                    new Assert\PositiveOrZero(),
                ],
            ])
            ->add('salaireMax', IntegerType::class, [
                'label' => 'Salaire maximum',
                'attr' => ['placeholder' => 'Ex: 350000', 'min' => 0],
                'required' => true,
                'constraints' => [
                    new Assert\NotNull(),
                    new Assert\PositiveOrZero(),
                ],
            ])
            ->add('dateExpirationAt', DateTimeType::class, [
                'label' => 'Date d\'expiration',
                'widget' => 'single_text',
                'required' => true,
                'invalid_message' => 'Saisissez une date et une heure d\'expiration complètes.',
                'attr' => [
                    'step' => 60,
                ],
                'constraints' => [
                    new Assert\NotNull(message: 'Saisissez la date et l\'heure d\'expiration.'),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'rows' => 8,
                    'placeholder' => "Décris le job de façon détaillée.",
                    'minlength' => 20,
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 20),
                ],
            ])
            ->add('mission', TextareaType::class, [
                'label' => 'Mission',
                'attr' => [
                    'rows' => 8,
                    'placeholder' => "Décris les missions, exigences, avantages...\n• Missions",
                    'minlength' => 20,
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 20),
                ],
            ])
            ->add('profil', TextareaType::class, [
                'label' => 'Profil recherché',
                'attr' => [
                    'rows' => 8,
                    'placeholder' => "Décris le profil...\n• Compétences\n• Avantages",
                    'minlength' => 20,
                ],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 20),
                ],
            ])
            ;

        // ✅ Ajout conditionnel : champs réservés aux admins/modérateurs
        if ($options['is_staff'] ?? false) {
            $builder
                ->add('status', EntityType::class, [
                    'label' => 'Statut',
                    'class' => StatusJob::class,
                    'choice_label' => 'statusJob',
                    'placeholder' => 'Choisir...',
                    'required' => false,
                ])
                
                ->add('adminModificationNote', TextareaType::class, [
                    'label' => 'Note de modification (visible uniquement par les admins)',
                    'attr' => [
                        'rows' => 3,
                        'placeholder' => 'Expliquez les raisons de cette modification...',
                        'class' => 'form-control',
                    ],
                    'required' => false,
                    
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Job::class,
            'is_staff' => false, // ✅ Par défaut, pas staff
        ]);

        $resolver->setAllowedTypes('is_staff', 'bool');
    }
}
