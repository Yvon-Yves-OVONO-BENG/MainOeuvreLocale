<?php

namespace App\Form;

use App\Entity\Categorie;
use App\Entity\Country;
use App\Entity\PersonalProfile;
use App\Entity\Profession;
use App\Entity\Sexe;
use App\Repository\CategorieRepository;
use App\Repository\CountryRepository;
use App\Repository\ProfessionRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Regex;

class AccountProfileEditType extends AbstractType
{
    public function __construct(
        private readonly CountryRepository $countryRepository,
        private readonly CategorieRepository $categorieRepository,
        private readonly ProfessionRepository $professionRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $mode = $options['mode']; // talent | company | particulier | moderateur | admin | superAdmin
        $isCompany = $mode === 'company';
        $identityRequired = in_array($mode, ['talent', 'particulier'], true);
        $talentRequired = $mode === 'talent';
        $photoRequired = $options['photo_required'];
        $cvRequired = $talentRequired && $options['cv_required'];
        $requiredMessage = 'Ce champ est obligatoire.';

        $photoConstraints = [
            new Image([
                'maxSize' => '12M',
                'mimeTypes' => [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'image/gif',
                ],
                'mimeTypesMessage' => 'Veuillez sélectionner une image valide.',
            ]),
        ];

        if ($photoRequired) {
            array_unshift($photoConstraints, new NotBlank(['message' => $requiredMessage]));
        }

        // === Champs USER (communs) ===
        $builder
            ->add('email', EmailType::class, [
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => $requiredMessage]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Email',
                ],
                'label' => 'Adresse email',
            ])
            ->add('country', EntityType::class, [
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotNull(['message' => 'Sélectionnez votre pays.']),
                ],
                'class' => Country::class,
                'choice_label' => 'country',
                'placeholder' => '-- Sélectionner --',
                'attr' => [
                    'class' => 'form-select mol-country-native-select',
                ],
                'label' => 'Pays',
                'choices' => $this->countryRepository->findUniqueNonEmptyOrdered(
                    !empty($options['country_id']) ? (int) $options['country_id'] : null
                ),
                'choice_attr' => function (Country $c): array {
                    $flagFile = trim((string) $c->getFlagFile());

                    return $flagFile !== ''
                        ? ['data-flag' => '/uploads/flags/' . $flagFile]
                        : [];
                },
            ])
            ->add('phone', TextType::class, [
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank(['message' => $requiredMessage]),
                ],
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Numéro de téléphone',
                ],
                'label' => 'Téléphone',
            ])
            ->add('photoFile', FileType::class, [
                'mapped' => false,
                'required' => $photoRequired,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/jpeg,image/png,image/webp,image/gif',
                ],
                'label' => $isCompany ? "Logo de l'entreprise" : 'Photo de profil',
                'constraints' => $photoConstraints,
            ]);

        // === TALENT / PARTICULIER / ADMIN... : champs identité personne ===
        if (in_array($mode, ['talent', 'particulier', 'moderateur', 'admin', 'superAdmin'], true)) {
            $builder
                ->add('fullName', TextType::class, [
                    'mapped' => true,
                    'required' => $identityRequired,
                    'constraints' => $identityRequired ? [
                        new NotBlank(['message' => 'Veuillez renseigner votre nom complet.']),
                        new Regex([
                            'pattern' => '/^(?!.*@).+$/u',
                            'message' => 'Veuillez renseigner votre nom complet, pas une adresse email.',
                        ]),
                    ] : [],
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Nom complet',
                    ],
                    'label' => 'Nom complet',
                ])
                ->add('adress', TextType::class, [
                    'mapped' => true,
                    'required' => $identityRequired,
                    'constraints' => $identityRequired ? [new NotBlank(['message' => $requiredMessage])] : [],
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Adresse',
                    ],
                    'label' => 'Adresse',
                ])
                ->add('city', TextType::class, [
                    'mapped' => true,
                    'required' => $identityRequired,
                    'constraints' => $identityRequired ? [new NotBlank(['message' => $requiredMessage])] : [],
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Ville',
                    ],
                    'label' => 'Ville',
                ])
                ->add('sexe', EntityType::class, [
                    'mapped' => true,
                    'required' => $identityRequired,
                    'constraints' => $identityRequired ? [new NotBlank(['message' => $requiredMessage])] : [],
                    'class' => Sexe::class,
                    'choice_label' => 'sexe',
                    'placeholder' => '-- Sélectionner --',
                    'attr' => ['class' => 'form-control form-select'],
                    'label' => 'Sexe',
                ])
                ->add('cniFile', FileType::class, [
                    'mapped' => false,
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'accept' => 'image/*,.pdf',
                    ],
                    'label' => 'CNI (Recto/Verso)',
                    'help' => 'Formats acceptés : JPG/PNG/WebP/PDF. Les images sont automatiquement compressées.',
                    'constraints' => [
                        new File([
                            'maxSize' => '12M',
                            'mimeTypes' => [
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                                'application/pdf',
                            ],
                            'mimeTypesMessage' => 'Veuillez uploader la CNI au format JPG, PNG, WebP ou PDF.',
                        ])
                    ],
                ]);
        }

        // === TALENT : ProfessionalProfile (mapped=false) ===
        if (in_array($mode, ['talent', 'moderateur', 'admin', 'superAdmin'], true)) {
            $builder
                ->add('bio', TextareaType::class, [
                    'mapped' => false,
                    'required' => $talentRequired,
                    'constraints' => $talentRequired ? [new NotBlank(['message' => $requiredMessage])] : [],
                    'attr' => [
                        'class' => 'form-control',
                        'rows' => 5,
                        'placeholder' => 'Description',
                    ],
                    'label' => 'À propos de moi',
                ])
                ->add('experience', TextareaType::class, [
                    'mapped' => false,
                    'required' => $talentRequired,
                    'constraints' => $talentRequired ? [new NotBlank(['message' => $requiredMessage])] : [],
                    'attr' => [
                        'class' => 'form-control',
                        'rows' => 5,
                        'placeholder' => 'Décrivez votre expérience',
                    ],
                    'label' => 'Votre expérience',
                ])
                ->add('experienceYears', TextType::class, [
                    'mapped' => false,
                    'required' => $talentRequired,
                    'constraints' => $talentRequired ? [new NotBlank(['message' => $requiredMessage])] : [],
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Ex: 3',
                    ],
                    'label' => "Années d'expérience",
                ])
                ->add('categorie', EntityType::class, [
                    'mapped' => false,
                    'required' => $talentRequired,
                    'constraints' => $talentRequired ? [new NotBlank(['message' => $requiredMessage])] : [],
                    'class' => Categorie::class,
                    'choice_label' => 'nom',
                    'placeholder' => '-- Sélectionner une catégorie --',
                    'choices' => $this->categorieRepository->findUniqueNonEmptyOrdered(
                        true,
                        !empty($options['categorie_id']) ? (int) $options['categorie_id'] : null
                    ),
                    'choice_attr' => function (Categorie $categorie) {
                        return ['data-icon' => $categorie->getIcon()];
                    },
                    'attr' => ['class' => 'form-control form-select js-categorie'],
                    'label' => 'Catégorie',
                ])
                ->add('profession', EntityType::class, [
                    'mapped' => false,
                    'required' => $talentRequired,
                    'constraints' => $talentRequired ? [new NotBlank(['message' => $requiredMessage])] : [],
                    'class' => Profession::class,
                    'choice_label' => 'profession',
                    'placeholder' => '-- Sélectionner une profession --',
                    'choices' => $this->professionRepository->findUniqueNonEmptyOrdered(
                        !empty($options['categorie_id']) ? (int) $options['categorie_id'] : null,
                        !empty($options['profession_id']) ? (int) $options['profession_id'] : null
                    ),
                    'attr' => ['class' => 'form-control form-select js-profession'],
                    'label' => 'Profession',
                ])
                ->add('skills', EntityType::class, [
                    'mapped' => false,
                    'class' => Profession::class,
                    'choice_label' => 'profession',
                    'multiple' => true,
                    'expanded' => true,
                    'required' => $talentRequired,
                    'constraints' => $talentRequired ? [
                        new Count(min: 1, minMessage: $requiredMessage),
                    ] : [],
                    'choices' => $this->professionRepository->findUniqueNonEmptyOrdered(),
                    'choice_attr' => static fn (Profession $profession): array => [
                        'class' => 'form-check-input',
                    ],
                    'attr' => [
                        'class' => 'mol-skills-checkboxes',
                    ],
                    'label' => 'Compétences (autres métiers)',
                ])
                ->add('cvFile', FileType::class, [
                    'mapped' => false,
                    'required' => $cvRequired,
                    'attr' => [
                        'class' => 'form-control',
                        'accept' => '*/*',
                    ],
                    'label' => 'Votre CV',
                    'constraints' => array_filter([
                        $cvRequired ? new NotBlank(['message' => $requiredMessage]) : null,
                        new File([
                            'maxSize' => '12M',
                            'mimeTypes' => [
                                'application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            ],
                            'mimeTypesMessage' => 'Veuillez uploader un CV au format PDF, DOC ou DOCX.',
                        ]),
                    ]),
                ]);
        }

        // Géolocalisation : uniquement pour les talents.
        // Ces champs sont appliqués manuellement sur ProfessionalProfile.
        if ($mode === 'talent') {
            $builder
                ->add('geolocationEnabled', CheckboxType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'Me rendre visible pour les missions à proximité',
                    'attr' => [
                        'class' => 'form-check-input mol-geo-toggle',
                        'role' => 'switch',
                    ],
                ])
                ->add('latitude', HiddenType::class, [
                    'mapped' => false,
                    'required' => false,
                ])
                ->add('longitude', HiddenType::class, [
                    'mapped' => false,
                    'required' => false,
                ]);
        }

        // === COMPANY : champs propres à l'entreprise (mappés à PersonalProfile) ===
        if ($mode === 'company') {
            $builder
                ->add('companyLegalName', TextType::class, [
                    'mapped' => true,
                    'required' => true,
                    'empty_data' => '',
                    'constraints' => [new NotBlank(['message' => $requiredMessage])],
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Raison sociale',
                    ],
                    'label' => 'Raison sociale',
                ])
                ->add('companyTradeName', TextType::class, [
                    'mapped' => true,
                    'required' => true,
                    'empty_data' => '',
                    'constraints' => [new NotBlank(['message' => $requiredMessage])],
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Nom commercial',
                    ],
                    'label' => 'Nom commercial',
                ])
                ->add('companyRegistrationNumber', TextType::class, [
                    'mapped' => true,
                    'required' => true,
                    'empty_data' => '',
                    'constraints' => [new NotBlank(['message' => $requiredMessage])],
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Numéro RCCM / immatriculation',
                    ],
                    'label' => "Numéro d'immatriculation",
                ])
                ->add('companyTaxNumber', TextType::class, [
                    'mapped' => true,
                    'required' => true,
                    'empty_data' => '',
                    'constraints' => [new NotBlank(['message' => $requiredMessage])],
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Numéro contribuable / IFU / NIU',
                    ],
                    'label' => 'Numéro fiscal',
                ])
                ->add('companyContactName', TextType::class, [
                    'mapped' => true,
                    'required' => true,
                    'empty_data' => '',
                    'constraints' => [new NotBlank(['message' => $requiredMessage])],
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Nom du responsable ou contact',
                    ],
                    'label' => 'Nom du contact',
                ])
                ->add('companyContactPhone', TextType::class, [
                    'mapped' => true,
                    'required' => false,
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Téléphone du contact (facultatif)',
                    ],
                    'label' => 'Téléphone du contact',
                ])
                ->add('companyWebsite', UrlType::class, [
                    'mapped' => true,
                    'required' => false,
                    'default_protocol' => 'https',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'https://www.monentreprise.com (facultatif)',
                    ],
                    'label' => 'Site web',
                ])
                ->add('companyDescription', TextareaType::class, [
                    'mapped' => true,
                    'required' => true,
                    'empty_data' => '',
                    'constraints' => [new NotBlank(['message' => $requiredMessage])],
                    'attr' => [
                        'class' => 'form-control',
                        'rows' => 5,
                        'placeholder' => "Présentation de l'entreprise",
                    ],
                    'label' => "Description de l'entreprise",
                ])
                ->add('adress', TextType::class, [
                    'mapped' => true,
                    'required' => false,
                    // La colonne historique est NOT NULL : une adresse absente est stockée en chaîne vide.
                    'empty_data' => '',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => "Adresse de l'entreprise (facultatif)",
                    ],
                    'label' => 'Adresse',
                ])
                ->add('city', TextType::class, [
                    'mapped' => true,
                    'required' => true,
                    'empty_data' => '',
                    'constraints' => [new NotBlank(['message' => $requiredMessage])],
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Ville',
                    ],
                    'label' => 'Ville',
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PersonalProfile::class,
            'mode' => 'particulier',
            'categorie_id' => null,
            'country_id' => null,
            'profession_id' => null,
            'photo_required' => false,
            'cv_required' => false,
        ]);

        $resolver->setAllowedTypes('photo_required', 'bool');
        $resolver->setAllowedTypes('cv_required', 'bool');
        $resolver->setAllowedTypes('categorie_id', ['null', 'int']);
        $resolver->setAllowedTypes('country_id', ['null', 'int']);
        $resolver->setAllowedTypes('profession_id', ['null', 'int']);

        $resolver->setAllowedValues('mode', [
            'talent',
            'company',
            'particulier',
            'moderateur',
            'admin',
            'superAdmin',
        ]);
    }
}
