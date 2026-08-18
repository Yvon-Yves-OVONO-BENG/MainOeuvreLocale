<?php

namespace App\Controller\Web\Profile;

use App\Entity\User;
use App\Entity\PersonalProfile;
use App\Entity\ProfessionalProfile;
use App\Security\UserAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

#[IsGranted('ROLE_USER')]
class ProfileTypeChoiceController extends AbstractController
{
    #[Route('/choisir-type-profil', name: 'profile_type_choice')]
    public function chooseProfileType(
        Request $request,
        EntityManagerInterface $em,
        UserAuthenticatorInterface $userAuthenticator,
        UserAuthenticator $authenticator // ✅ Votre authenticateur directement
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Si l'utilisateur a déjà choisi son type de profil
        if ($user->getProfileType() !== null && $user->getProfileType() !== '') {
            return $this->redirectToRoute('profile_edit');
        }

        // Créer le formulaire de choix
        $form = $this->createFormBuilder()
            ->add('profileType', ChoiceType::class, [
                'label' => false,
                'choices' => [
                    '🧑‍💼 Talent - Je cherche du travail' => 'talent',
                    '👤 Particulier - Je cherche des services' => 'particulier',
                    '🏢 Entreprise - Je recrute ou propose des services' => 'company',
                ],
                'expanded' => true,
                'multiple' => false,
                'attr' => ['class' => 'profile-type-choices']
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Continuer',
                'attr' => ['class' => 'btn-premium-submit']
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $profileType = $data['profileType'];

            // Enregistrer le type de profil dans l'utilisateur
            $user->setProfileType($profileType);
            
            // Récupérer les rôles actuels
            $roles = $user->getRoles();
            
            // Supprimer les anciens rôles de profil
            $roles = array_diff($roles, ['ROLE_TALENT', 'ROLE_PARTICULIER', 'ROLE_COMPANY']);
            
            // Ajouter le nouveau rôle
            switch ($profileType) {
                case 'talent':
                    $roles[] = 'ROLE_TALENT';
                    break;
                case 'company':
                    $roles[] = 'ROLE_COMPANY';
                    break;
                case 'particulier':
                    $roles[] = 'ROLE_PARTICULIER';
                    break;
            }
            
            $user->setRoles(array_values($roles));
            
            // Créer un PersonalProfile si pas encore
            if (!$user->getPersonalProfile()) {
                $personalProfile = new PersonalProfile();
                $personalProfile->setUser($user);
                // Valeurs techniques vides : le formulaire suivant impose le vrai nom et la vraie photo.
                // On n'utilise surtout pas l'email comme nom public.
                $personalProfile->setFullName('');
                $personalProfile->setPhoto('');
                // Le champ slug est obligatoire en base. Il doit être créé
                // avant le premier flush, quel que soit le type de profil choisi.
                $personalProfile->setSlug(\App\Util\HashedSlugGenerator::generate());
                
                if (method_exists($personalProfile, 'setAdress')) {
                    $personalProfile->setAdress('');
                }
                if (method_exists($personalProfile, 'setCity')) {
                    $personalProfile->setCity('');
                }
                
                $em->persist($personalProfile);
                $user->setPersonalProfile($personalProfile);
            }

            // Si Talent, créer un ProfessionalProfile
            if ($profileType === 'talent') {
                $professionalProfile = $user->getProfessionalProfile();
                if (!$professionalProfile) {
                    $professionalProfile = new ProfessionalProfile();
                    $professionalProfile->setUser($user);
                    $professionalProfile->setIsVerified(false);
                    $professionalProfile->setCreatedAt(new \DateTime());
                    
                    $professionalProfile->setBio('');
                    $professionalProfile->setExperience('');
                    $professionalProfile->setExperienceYears(0);
                    $professionalProfile->setCv('');
                    
                    if (method_exists($professionalProfile, 'setLatitude')) {
                        $professionalProfile->setLatitude('0');
                    }
                    if (method_exists($professionalProfile, 'setLongitude')) {
                        $professionalProfile->setLongitude('0');
                    }
                    if (method_exists($professionalProfile, 'setRatingAvg')) {
                        $professionalProfile->setRatingAvg(0);
                    }
                    
                    $em->persist($professionalProfile);
                }
            }
            
            // ✅ Sauvegarder en base
            $em->flush();

            // ✅ RÉAUTHENTIFICATION AUTOMATIQUE AVEC VOTRE AUTHENTICATEUR
            try {
                $userAuthenticator->authenticateUser(
                    $user,
                    $authenticator,
                    $request
                );
            } catch (\Exception $e) {
                // Si la réauthentification échoue, on redirige vers la connexion
                $this->addFlash('warning', 'Veuillez vous reconnecter pour continuer.');
                return $this->redirectToRoute('app_login');
            }

            $this->addFlash('success', 'Votre profil a été créé avec succès ! Vous pouvez maintenant le compléter.');

            return $this->redirectToRoute('profile_edit');
        }

        return $this->render('profile/choose_profile_type.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }
}
