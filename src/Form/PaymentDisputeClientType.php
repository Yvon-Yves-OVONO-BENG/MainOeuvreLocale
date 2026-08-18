<?php

namespace App\Form;

use App\Entity\Payment;
use App\Entity\PaymentDispute;
use App\Entity\User;
use App\Repository\PaymentRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentDisputeClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var User|null $user */
        $user = $options['user'];

        $builder
            ->add('payment', EntityType::class, [
                'class' => Payment::class,
                'placeholder' => 'Choisir un paiement',
                'query_builder' => function (PaymentRepository $paymentRepository) use ($user) {
                    return $paymentRepository->createQueryBuilder('p')
                        ->andWhere('p.user = :user')
                        ->setParameter('user', $user)
                        ->orderBy('p.id', 'DESC');
                },
                'choice_label' => function (Payment $payment): string {
                    $provider = method_exists($payment, 'getProvider') && $payment->getProvider()
                        ? ($payment->getProvider()->getProvider() ?? '—')
                        : '—';

                    $amount = method_exists($payment, 'getAmount') ? $payment->getAmount() : '—';
                    $currency = method_exists($payment, 'getCurrency') ? ($payment->getCurrency() ?? 'FCFA') : 'FCFA';
                    $date = method_exists($payment, 'getPaidAt') && $payment->getPaidAt()
                        ? $payment->getPaidAt()->format('d/m/Y H:i')
                        : '';

                    return sprintf('#%s • %s %s • %s • %s', $payment->getId(), $amount, $currency, $provider, $date);
                },
                'attr' => [
                    'class' => 'form-select form-select-lg',
                ],
            ])
            ->add('priority', ChoiceType::class, [
                'label' => 'Priorité',
                'placeholder' => 'Choisir une priorité',
                'choices' => [
                    'Faible' => PaymentDispute::PRIORITY_LOW,
                    'Moyenne' => PaymentDispute::PRIORITY_MEDIUM,
                    'Élevée' => PaymentDispute::PRIORITY_HIGH,
                ],
                'attr' => [
                    'class' => 'form-select form-select-lg',
                ],
            ])
            ->add('reason', TextareaType::class, [
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 7,
                    'placeholder' => 'Décrivez précisément le problème : montant débité, paiement non reconnu, facture absente, double débit, etc.',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PaymentDispute::class,
            'user' => null,
        ]);

        $resolver->setAllowedTypes('user', ['null', User::class]);
    }
}