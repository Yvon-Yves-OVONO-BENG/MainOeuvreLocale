<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

class NotificationSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $b
            ->add('emailEnabled', CheckboxType::class, ['required'=>false, 'label'=>false])
            ->add('smsEnabled', CheckboxType::class, ['required'=>false, 'label'=>false])
            ->add('whatsappEnabled', CheckboxType::class, ['required'=>false, 'label'=>false])

            ->add('evNewApplication', CheckboxType::class, ['required'=>false, 'label'=>false])
            ->add('evNewMessage', CheckboxType::class, ['required'=>false, 'label'=>false])
            ->add('evJobExpiring', CheckboxType::class, ['required'=>false, 'label'=>false])

            ->add('digestEnabled', CheckboxType::class, ['required'=>false, 'label'=>false])
            ->add('digestFrequency', ChoiceType::class, [
                'choices' => ['Quotidien' => 'daily', 'Hebdomadaire' => 'weekly'],
                'label' =>false,
            ]);
    }
}