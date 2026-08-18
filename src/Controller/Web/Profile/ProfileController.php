<?php

namespace App\Controller\Web\Profile;

use App\Entity\PersonalProfile;
use App\Entity\CompanyKycCase;
use App\Entity\ProfessionalProfile;
use App\Entity\Profession;
use App\Service\CvUploader;
use App\Service\FileUploader;
use App\Service\TalentGeolocationService;
use App\Form\AccountProfileEditType;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProfileController extends AbstractController
{
    public function __construct(
        protected TranslatorInterface $translator,
    ) {}

    #[Route('/profile-edit', name: 'profile_edit')]
    #[IsGranted('ROLE_USER')]
    public function edit(
        Request $request,
        EntityManagerInterface $em,
        FileUploader $uploader,
        CvUploader $cvUploader,
        TalentGeolocationService $geolocationService,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('Utilisateur non connecté.');
        }

        // ✅ Vérifier si l'utilisateur a choisi son type de profil
        if ($user->getProfileType() === null || $user->getProfileType() === '') {
            return $this->redirectToRoute('profile_type_choice');
        }


        // 1) Déterminer le mode par rôle
        $mode = 'particulier';
        
        if (in_array('ROLE_TALENT', $user->getRoles(), true)) {
            $mode = 'talent';
        } elseif (
            in_array('ROLE_COMPANY', $user->getRoles(), true) ||
            in_array('ROLE_ENTREPRISE', $user->getRoles(), true)
        ) {
            $mode = 'company';
        } elseif (
            in_array('ROLE_MODERATEUR', $user->getRoles(), true) 
        ) {
            $mode = 'moderateur';
        }
        elseif (
            in_array('ROLE_ADMIN', $user->getRoles(), true) 
        ) {
            $mode = 'admin';
        } elseif (
            in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true) 
        ) {
            $mode = 'superAdmin';
        }

        // 2) PersonalProfile (lié au form)
        $personal = $user->getPersonalProfile();
        if (!$personal) {
            $personal = new PersonalProfile();
            $personal->setUser($user);
            // Filet de sécurité pour les anciens comptes sans PersonalProfile :
            // la colonne slug est NOT NULL et doit être renseignée avant le flush.
            $personal->setSlug(\App\Util\HashedSlugGenerator::generate());
            $em->persist($personal);
            $user->setPersonalProfile($personal);
        }

        // ✅ Fix DB: si full_name est NOT NULL, on doit toujours avoir une valeur
        // Company n'est pas concernée par fullName, donc on met une valeur technique.
        // Je vais aussi mettre $companyName si tu veux.
        if ($mode === 'company' && method_exists($personal, 'setFullName')) {
            if (!$personal->getFullName()) {
                $personal->setFullName('—'); // valeur neutre / technique
            }
        }


        // 3) ProfessionalProfile (talent)
        $pro = $user->getProfessionalProfile();
        if ($mode === 'talent' && !$pro) {
            $pro = new ProfessionalProfile();
            $pro->setUser($user);
            $pro->setIsVerified(false);
            $pro->setCreatedAt(new DateTime('now'));

            $em->persist($pro);
            $user->setProfessionalProfile($pro);
        }

       // 4) Company (company) : désormais stocké dans PersonalProfile
        $company = null;

        if ($mode === 'company') {
            $company = $personal; // ✅ l’entreprise = le profil perso

            // ✅ Fix NOT NULL : fullName/photo
            if (!$company->getFullName()) {
                $company->setFullName('—'); // ou $user->getEmail()
            }
            // if (!$company->getPhoto()) {
            //     $company->setPhoto('default-avatar.png');
            // }
        }

        // 5) Créer le formulaire : IMPORTANT -> on passe $personal (data_class = PersonalProfile)
        $submitted = $request->request->all();
        $payload = $submitted['account_profile_edit'] ?? [];

        // ✅ Profession actuelle du profil
        $currentProfession = null;
        $currentCategorie = null;

        if ($mode === 'talent' && $pro && $pro->getProfession()) {
            $currentProfession = $pro->getProfession();

            // La catégorie vient de la profession
            $currentCategorie = $currentProfession->getCategorie();
        }

        // Symfony envoie les identifiants HTML sous forme de chaînes : on normalise avant OptionsResolver.
        if (array_key_exists('categorie', $payload)) {
            $rawCategorieId = $payload['categorie'];
            $validatedCategorieId = is_scalar($rawCategorieId)
                ? filter_var($rawCategorieId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                : false;
            $categorieId = $validatedCategorieId === false ? null : (int) $validatedCategorieId;
        } else {
            $categorieId = $currentCategorie?->getId();
        }

        $photoValue = trim((string) $personal->getPhoto());
        $photoBasename = strtolower((string) pathinfo(
            str_replace('\\', '/', $photoValue),
            PATHINFO_BASENAME
        ));
        $hasRealPhoto = $photoValue !== '' && !in_array(
            $photoBasename,
            ['avatar.png', 'default-avatar.png', 'default.png'],
            true
        );

        $photoRequired = in_array($mode, ['talent', 'particulier', 'company'], true)
            && !$hasRealPhoto;
        $cvRequired = $mode === 'talent'
            && (!$pro || trim((string) $pro->getCv()) === '');

        $form = $this->createForm(AccountProfileEditType::class, $personal, [
            'mode' => $mode,
            'categorie_id' => $categorieId,
            'country_id' => $user->getCountry()?->getId(),
            'profession_id' => $currentProfession?->getId(),
            // Un fichier réellement enregistré reste valable en modification.
            'photo_required' => $photoRequired,
            'cv_required' => $cvRequired,
        ]);

        // 6) Pré-remplir les champs USER (mapped=false)
        if ($form->has('email')) {
            $form->get('email')->setData($user->getEmail());
        }
        if ($form->has('phone')) {
            $form->get('phone')->setData($user->getPhone());
        }

        // ✅ COUNTRY (User)
        if ($form->has('country')) {
            $form->get('country')->setData($user->getCountry());
        }

        // 7) Pré-remplir les champs TALENT de ProfessionalProfile (mapped=false dans le form)
        if ($mode === 'talent' && $pro) {
            if ($form->has('bio')) {
                $form->get('bio')->setData($pro->getBio());
            }
            if ($form->has('experience')) {
                $form->get('experience')->setData($pro->getExperience());
            }
            if ($form->has('experienceYears')) {
                $form->get('experienceYears')->setData($pro->getExperienceYears());
            }

            // ✅ Catégorie pré-remplie depuis la profession
            if ($form->has('categorie')) {
                $form->get('categorie')->setData($currentCategorie);
            }

            // ✅ Profession pré-remplie
            if ($form->has('profession')) {
                $form->get('profession')->setData($currentProfession);
            }

            if ($form->has('skills')) {
                $form->get('skills')->setData($pro->getSkills());
            }
            if ($form->has('geolocationEnabled')) {
                $form->get('geolocationEnabled')->setData($pro->isGeolocationEnabled());
            }
            if ($form->has('latitude')) {
                $form->get('latitude')->setData($pro->getLatitude());
            }
            if ($form->has('longitude')) {
                $form->get('longitude')->setData($pro->getLongitude());
            }

        }

        // 9) Handle request
        // Conserver l'ancienne immatriculation avant le mapping Symfony permet de
        // rouvrir automatiquement une vérification KYC si l'entreprise la modifie.
        $previousCompanyRegistrationNumber = $mode === 'company'
            ? trim((string) $personal->getCompanyRegistrationNumber())
            : '';

        $form->handleRequest($request);

        // 10) Sauvegarde
        if ($form->isSubmitted() && $form->isValid())
        {
                // dd($form->get('profession'));
                // USER (mapped=false)
                if ($form->has('email')) {
                    $user->setEmail((string) $form->get('email')->getData());
                }
                if ($form->has('phone')) {
                    $user->setPhone((string) $form->get('phone')->getData());
                }
                if (method_exists($user, 'setUpdatedAt')) {
                    $user->setUpdatedAt(new \DateTime());
                }
                // ✅ COUNTRY (User)
                if ($form->has('country')) {
                    // Si ton champ country est un EntityType => getData() renvoie l'entité Country
                    $user->setCountry($form->get('country')->getData());
                }

                // ✅ PersonalProfile est mappé => fullName/adress/city/sexe sont déjà mis à jour automatiquement
                // Pas besoin de refaire $personal->set... ici.

                if ($pro && $form->has('cvFile')) 
                {
                    $cvFile = $form->get('cvFile')->getData();

                    if ($cvFile) {
                        // 1) Préparer le chemin de l'ancien fichier.
                        $oldCv = $pro->getCv();
                        $oldPath = null;
                        if ($oldCv) {
                            $oldPath = rtrim($this->getParameter('cv_upload_dir'), DIRECTORY_SEPARATOR)
                                . DIRECTORY_SEPARATOR
                                . $oldCv;
                        }

                        // 2) Générer le nom basé sur le talent
                        $talentName = $personal->getFullName() ?: ($user->getEmail() ?: 'talent');

                        // 3) Upload du nouveau fichier
                        $filename = $cvUploader->upload($cvFile, $talentName, $user->getId());

                        // 4) Supprimer l'ancien uniquement après le succès du nouvel upload.
                        if ($oldPath) {
                            $fs = new Filesystem();
                            if ($fs->exists($oldPath)) {
                                $fs->remove($oldPath);
                            }
                        }

                        // 5) Enregistrer en DB
                        $pro->setCv($filename);
                    }

                }

                // =====================
                // PHOTO (Talent, Particulier, Modérateur, Admin...)
                // =====================
                if (
                    $mode !== 'company'
                    && $form->has('photoFile')
                ) {
                    $photoFile = $form->get('photoFile')->getData();

                    if ($photoFile) {
       
                        $filename = $uploader->upload($photoFile, 'user-' . $user->getId());

                        $personal->setPhoto($filename);
                    }
                }

                // TALENT (mapped=false dans le form) => on applique manuellement sur $pro
                if (($mode === 'talent' && $pro && $form->has('cvFile'))) 
                {
                    if ($form->has('bio')) {
                        $pro->setBio((string) $form->get('bio')->getData());
                    }

                    if ($form->has('experienceYears')) {
                        $pro->setExperienceYears((string) $form->get('experienceYears')->getData());
                    }

                    if ($form->has('experience')) {
                        $pro->setExperience((string) $form->get('experience')->getData());
                        $pro->setCreatedAt(new \DateTime('now'));
                    }


                    // ✅ profession (attention au champ disabled côté HTML)
                    // ✅ PROFESSION : on lit l'id soumis et on fait un find() (fiable même si choices=[] / disabled)
                    if ($form->has('profession')) {
                        $submitted = $request->request->all();
                        $payload = $submitted['account_profile_edit'] ?? [];
                        $professionId = $payload['profession'] ?? null;

                        if ($professionId !== null && $professionId !== '') {
                            $profession = $em->getRepository(Profession::class)->find((int) $professionId);
                            $pro->setProfession($profession);
                        } else {
                            // si l'utilisateur n'a rien choisi
                            $pro->setProfession(null);
                        }
                    }

                    // ✅ SKILLS (ManyToMany) : appliquer manuellement sur $pro
                    // ✅ SKILLS (mapped=false) => sync ManyToMany vers table pivot
                    if ($form->has('skills')) {
                        /** @var \Doctrine\Common\Collections\Collection<int, \App\Entity\Profession> $selectedSkills */
                        $selectedSkills = $form->get('skills')->getData();

                        // 1) retirer ceux qui ne sont plus sélectionnés
                        foreach ($pro->getSkills() as $existing) {
                            if (!$selectedSkills->contains($existing)) {
                                $pro->removeSkill($existing);
                            }
                        }

                        // 2) ajouter les nouveaux
                        foreach ($selectedSkills as $skill) {
                            $pro->addSkill($skill);
                        }
                    }

                    if ($form->has('geolocationEnabled')) {
                        $enabled = (bool) $form->get('geolocationEnabled')->getData();
                        $latitude = $form->has('latitude') ? $form->get('latitude')->getData() : null;
                        $longitude = $form->has('longitude') ? $form->get('longitude')->getData() : null;

                        try {
                            $geolocationService->applyConsent(
                                $pro,
                                $enabled,
                                $latitude,
                                $longitude
                            );
                        } catch (\InvalidArgumentException $exception) {
                            $pro->disableGeolocation();
                            $this->addFlash('warning', $exception->getMessage());
                        }
                    }

                }

                // =====================
                // CNI (Talent + Particulier) — optionnel
                // =====================
                if (in_array($mode, ['talent', 'particulier', 'moderateur', 'admin', 'superAdmin'], true) && $form->has('cniFile')) {

                    /** @var UploadedFile|null $cniFile */
                    $cniFile = $form->get('cniFile')->getData();

                    if ($cniFile) {

                        // 1) supprimer l'ancienne CNI si elle existe (si tu stockes le nom du fichier en DB)
                        // ⚠️ adapte getCni()/setCni() à ton entité (PersonalProfile ou ProfessionalProfile)
                        if (method_exists($personal, 'getCni') && method_exists($personal, 'setCni')) {

                            $oldCni = $personal->getCni();
                            $oldPath = null;
                            if ($oldCni) {
                                $oldPath = rtrim($this->getParameter('cni_upload_dir'), DIRECTORY_SEPARATOR)
                                    . DIRECTORY_SEPARATOR
                                    . $oldCni;
                            }

                            // 2) upload nouvelle CNI
                            // 👉 On réutilise ton FileUploader (comme photo), mais avec un "prefix" propre
                            $cniFilename = $uploader->upload($cniFile, 'cni-user-' . $user->getId(), 'cni');

                            // 3) supprimer l'ancienne CNI après le succès du nouvel upload
                            if ($oldPath) {
                                $fs = new Filesystem();
                                if ($fs->exists($oldPath)) {
                                    $fs->remove($oldPath);
                                }
                            }

                            // 4) enregistrer en DB
                            $personal->setCni($cniFilename);
                        }

                        // (Optionnel) si tu veux marquer le talent comme “à vérifier”
                        // sans changer ton workflow actuel :
                        if ($mode === 'talent' && $pro && method_exists($pro, 'setIsVerified')) {
                            // Ne le passe PAS à true automatiquement (c’est un doc à valider).
                            // Ici on ne casse rien : on peut juste laisser false, ou déclencher un flag "pending" si tu l’as.
                            // $pro->setIsVerified(false);
                        }
                    }
                }

                if ($mode === 'company' && $company instanceof PersonalProfile) {
                    if ($form->has('photoFile')) {
                        $photoFile = $form->get('photoFile')->getData();

                        if ($photoFile) {
                            $filename = $uploader->upload($photoFile, 'company-' . $user->getId());
                            $company->setPhoto($filename);
                        }
                    }

                    // sécurité si fullName est NOT NULL en base
                    if (!$company->getFullName()) {
                        $company->setFullName(
                            $company->getCompanyTradeName()
                            ?: $company->getCompanyLegalName()
                            ?: '—'
                        );
                    }

                }

                // On sauvegarde d'abord le profil : une indisponibilité du module KYC
                // ne doit jamais empêcher l'entreprise d'enregistrer ses informations.
                $em->flush();

                if ($mode === 'company' && $company instanceof PersonalProfile) {
                    /*
                     * Le module KYC entreprise existe déjà dans l'administration.
                     * À partir d'une immatriculation renseignée, on crée ou actualise
                     * automatiquement le dossier afin qu'un administrateur puisse
                     * réellement vérifier le RCCM / numéro d'immatriculation.
                     */
                    $registrationNumber = trim((string) $company->getCompanyRegistrationNumber());

                    if ($registrationNumber !== '') {
                        try {
                            $kycRepository = $em->getRepository(CompanyKycCase::class);
                            /** @var CompanyKycCase|null $kycCase */
                            $kycCase = $kycRepository->findOneBy(
                                ['company' => $user],
                                ['createdAt' => 'DESC']
                            );

                            $registrationChanged =
                                strcasecmp($previousCompanyRegistrationNumber, $registrationNumber) !== 0;

                            if (!$kycCase) {
                                $kycCase = (new CompanyKycCase())
                                    ->setCompany($user)
                                    ->setStatus(CompanyKycCase::STATUS_PENDING)
                                    ->setSubmittedAt(new \DateTimeImmutable());

                                $em->persist($kycCase);
                            } elseif ($registrationChanged) {
                                // Une immatriculation modifiée doit être vérifiée à nouveau.
                                $kycCase
                                    ->setStatus(CompanyKycCase::STATUS_PENDING)
                                    ->setReviewedAt(null)
                                    ->setReviewedBy(null)
                                    ->setSubmittedAt(new \DateTimeImmutable());
                            } elseif ($kycCase->getSubmittedAt() === null) {
                                $kycCase->setSubmittedAt(new \DateTimeImmutable());
                            }

                            $kycCase
                                ->setCompanyName(
                                    $company->getCompanyTradeName()
                                    ?: $company->getCompanyLegalName()
                                    ?: null
                                )
                                ->setCity($company->getCity() ?: null);

                            $em->flush();
                        } catch (\Throwable) {
                            // Le profil reste sauvegardé même si la file KYC est momentanément indisponible.
                            $this->addFlash(
                                'warning',
                                "Votre profil est enregistré, mais la vérification du numéro d'immatriculation n'a pas pu être mise en file. Réessayez plus tard."
                            );
                        }
                    }
                }

            $this->addFlash('success', 'Profil mis à jour.');
            return $this->redirectToRoute('profile_edit');
        }

        if ($form->isSubmitted() && !$form->isValid()) {
            $this->addFlash(
                'danger',
                'Le profil n’a pas été enregistré. Corrigez les champs signalés en rouge.'
            );
        }

        return $this->render('profile/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
            'personal' => $personal,
            'pro' => $pro,
            'mode' => $mode,
            'photoRequired' => $photoRequired,
            'cvRequired' => $cvRequired,
        ]);
    }
}
