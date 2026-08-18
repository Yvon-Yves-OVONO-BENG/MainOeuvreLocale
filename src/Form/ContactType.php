<?php

namespace App\Form;

use App\Model\ContactRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactType extends AbstractType
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $inputClass = 'form-control';
        $selectClass = 'form-select';
        $locale = strtolower((string) ($this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr'));
        $isEnglish = str_starts_with($locale, 'en');
        $subjectPlaceholder = $isEnglish ? 'Choose a topic…' : 'Choisir un sujet…';
        $subjectChoices = $isEnglish ? [
            'Technical support' => 'support',
            'Account & verification' => 'account',
            'Payment / subscription' => 'payment',
            'Partnership' => 'partnership',
            'Business request' => 'business',
            'Other request' => 'other',
        ] : [
            'Support technique' => 'support',
            'Compte & vérification' => 'account',
            'Paiement / abonnement' => 'payment',
            'Partenariat' => 'partnership',
            'Demande commerciale' => 'business',
            'Autre demande' => 'other',
        ];

        $builder
            ->add('name', TextType::class, [
                'required' => true,
                'label' => 'Nom complet',
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => 'Ex : OVONO BENG Yves Noël',
                    'autocomplete' => 'name',
                    'inputmode' => 'text',
                    'autocapitalize' => 'words',
                    'pattern' => "[A-Za-zÀ-ÿ\\s'’\\-]+",
                    'data-name-validation' => '1',
                ],
            ])
            ->add('email', EmailType::class, [
                'required' => true,
                'label' => 'Adresse email',
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => 'Ex : contact@exemple.com',
                    'autocomplete' => 'email',
                ],
            ])
            ->add('phone', TelType::class, [
                'required' => false,
                'label' => 'Téléphone',
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => '+237 6XX XXX XXX',
                    'autocomplete' => 'tel',
                ],
            ])
            ->add('subject', ChoiceType::class, [
                'required' => true,
                'label' => 'Sujet',
                'placeholder' => $subjectPlaceholder,
                'choices' => $subjectChoices,
                'attr' => [
                    'class' => $selectClass,
                ],
            ])
            ->add('message', TextareaType::class, [
                'required' => true,
                'label' => 'Votre message',
                'attr' => [
                    'class' => $inputClass,
                    'placeholder' => 'Bonjour, je vous contacte au sujet de…',
                    'rows' => 6,
                ],
            ])
            ->add('consent', CheckboxType::class, [
                'required' => true,
                'label' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactRequest::class,
            'csrf_protection' => true,
        ]);
    }
}