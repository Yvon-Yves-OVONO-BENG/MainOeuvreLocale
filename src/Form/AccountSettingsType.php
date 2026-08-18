<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class AccountSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Email affiché (souvent on le laisse en readonly)
            ->add('email', EmailType::class, [
                'disabled' => true,
                'label' => 'Email',
            ])
            ->add('phone', TelType::class, [
                'label' => 'Téléphone',
                'constraints' => [
                    new NotBlank(message: 'Téléphone requis.'),
                    new Length(min: 9, max: 20),
                    // Exemple Cameroun (6xxxxxxxx ou 2xxxxxxxx) — adapte si tu veux international
                    new Regex(pattern: '/^(6|2)\d{8}$/', message: 'Format téléphone invalide (ex: 6xxxxxxxx).'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}