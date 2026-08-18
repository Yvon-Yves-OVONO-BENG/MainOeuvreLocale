<?php

namespace App\Form;

use App\Entity\Annonce;
use App\Entity\Categorie;
use App\Entity\Profession;
use App\Repository\CategorieRepository;
use App\Repository\ProfessionRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AnnonceType extends AbstractType
{
    public function __construct(
        private readonly ProfessionRepository $professionRepository,
        private readonly CategorieRepository $categorieRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre de l’annonce',
                'attr' => [
                    'placeholder' => 'Ex. Menuisier disponible immédiatement à Bafoussam',
                    'minlength' => 3,
                    'maxlength' => 180,
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Saisissez un métier, un service ou une disponibilité.'),
                    new Assert\Length(min: 3, max: 180),
                    new Assert\Regex(
                        pattern: "/^(?!\s*\d+\s*$)[\p{L}\p{M}0-9 .,'’+\-\/()]+$/u",
                        message: 'Utilisez un intitulé clair. Les chiffres seuls ne sont pas acceptés.'
                    ),
                ],
            ])
            ->add('ville', TextType::class, [
                'label' => 'Ville',
                'attr' => [
                    'placeholder' => 'Ex. Yaoundé',
                    'maxlength' => 120,
                    'autocomplete' => 'address-level2',
                ],
            ])
            ->add('salaireJournalier', IntegerType::class, [
                'label' => 'Salaire journalier (FCFA)',
                'required' => true,
                'help' => 'Indiquez le montant souhaité pour une journée de travail.',
                'attr' => [
                    'placeholder' => 'Ex. 15 000',
                    'min' => 1,
                    'max' => 100000000,
                    'step' => 500,
                    'inputmode' => 'numeric',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('categorie', EntityType::class, [
                'label' => 'Catégorie',
                'class' => Categorie::class,
                'choice_label' => 'nom',
                'placeholder' => 'Sélectionnez une catégorie',
                'choices' => $this->categorieRepository->findUniqueNonEmptyOrdered(
                    true,
                    $options['data'] instanceof Annonce ? $options['data']->getCategorie()?->getId() : null
                ),
                'attr' => [
                    'class' => 'form-select js-annonce-categorie',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Ce que vous proposez aujourd’hui',
                'attr' => [
                    'rows' => 7,
                    'maxlength' => 5000,
                    'placeholder' => 'Présentez clairement votre service, votre disponibilité, une promotion ou une offre actuelle…',
                ],
            ])
            ->add('afficherMaintenant', CheckboxType::class, [
                'label' => 'Afficher maintenant',
                'required' => false,
                'help' => 'Si vous activez ce switch, l’annonce sera envoyée à la modération avant sa publication.',
                'attr' => [
                    'class' => 'form-check-input js-premium-switch',
                    'role' => 'switch',
                ],
            ]);

        $addProfessionField = function (FormInterface $form, ?Categorie $categorie, ?Profession $selectedProfession = null): void {
            $form->add('profession', EntityType::class, [
                'label' => 'Profession',
                'class' => Profession::class,
                'choice_label' => 'profession',
                'choices' => $categorie
                    ? $this->professionRepository->findUniqueNonEmptyOrdered(
                        (int) $categorie->getId(),
                        $selectedProfession?->getId()
                    )
                    : [],
                'placeholder' => $categorie
                    ? 'Sélectionnez une profession'
                    : 'Choisissez d’abord une catégorie',
                'attr' => [
                    'class' => 'form-select js-annonce-profession',
                    'data-placeholder-empty' => 'Choisissez d’abord une catégorie',
                ],
            ]);
        };

        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) use ($addProfessionField): void {
            $data = $event->getData();
            $addProfessionField(
                $event->getForm(),
                $data instanceof Annonce ? $data->getCategorie() : null,
                $data instanceof Annonce ? $data->getProfession() : null
            );
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($addProfessionField): void {
            $data = $event->getData();
            $categoryId = is_array($data) ? (int) ($data['categorie'] ?? 0) : 0;
            $professionId = is_array($data) ? (int) ($data['profession'] ?? 0) : 0;
            $categorie = $categoryId > 0 ? $this->categorieRepository->find($categoryId) : null;
            $selectedProfession = $professionId > 0
                ? $this->professionRepository->find($professionId)
                : null;

            $addProfessionField($event->getForm(), $categorie, $selectedProfession);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Annonce::class,
        ]);
    }
}
