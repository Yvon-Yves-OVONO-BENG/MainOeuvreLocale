<?php

namespace App\Form;

use App\Entity\Invoices;
use App\Entity\Payment;
use App\Entity\PaymentDispute;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PaymentDisputeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isAdmin = (bool) ($options['is_admin'] ?? false);

        $builder
            ->add('payment', EntityType::class, [
                'class' => Payment::class,
                'choice_label' => static function (Payment $payment): string {
                    $amount = $payment->getAmount() ?? '0';
                    $currency = $payment->getCurrency() ?? '';
                    $date = $payment->getPaidAt() ? $payment->getPaidAt()->format('d/m/Y H:i') : '';
                    return trim($amount . ' ' . $currency . ' • ' . $date);
                },
                'label' => 'Paiement',
                'placeholder' => 'Sélectionner un paiement',
            ])
            ->add('invoice', EntityType::class, [
                'class' => Invoices::class,
                'choice_label' => static function (Invoices $invoice): string {
                    return $invoice->getInvoiceNumber() ?? ('Facture #' . $invoice->getId());
                },
                'label' => 'Facture',
                'placeholder' => 'Aucune facture',
                'required' => false,
            ])
            ->add('reason', TextareaType::class, [
                'label' => 'Motif du litige',
                'attr' => [
                    'rows' => 6,
                    'placeholder' => 'Décris clairement le problème rencontré...',
                ],
            ]);

        if ($isAdmin) {
            $builder
                ->add('priority', ChoiceType::class, [
                    'label' => 'Priorité',
                    'choices' => [
                        'Low' => PaymentDispute::PRIORITY_LOW,
                        'Medium' => PaymentDispute::PRIORITY_MEDIUM,
                        'High' => PaymentDispute::PRIORITY_HIGH,
                    ],
                ])
                ->add('status', ChoiceType::class, [
                    'label' => 'Statut',
                    'choices' => [
                        'Ouvert' => PaymentDispute::STATUS_OPEN,
                        'En revue' => PaymentDispute::STATUS_REVIEW,
                        'Résolu' => PaymentDispute::STATUS_RESOLVED,
                        'Rejeté' => PaymentDispute::STATUS_REJECTED,
                    ],
                ])
                ->add('adminNote', TextareaType::class, [
                    'label' => 'Note admin',
                    'required' => false,
                    'attr' => [
                        'rows' => 4,
                        'placeholder' => 'Observation interne...',
                    ],
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PaymentDispute::class,
            'is_admin' => false,
        ]);
    }
}