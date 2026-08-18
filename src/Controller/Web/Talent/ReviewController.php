<?php

namespace App\Controller\Web\Talent;

use App\Entity\User;
use App\Entity\Review;
use App\Entity\ReviewScore;
use App\Repository\ReviewCriteriaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;

class ReviewController extends AbstractController
{
    
    #[Route('/review/create/{slug}', name: 'review_create', requirements: ['slug' => '[a-f0-9]{64}'])]
    public function create(
        #[MapEntity(mapping: ['slug' => 'slug'])] User $user,
        Request $request,
        ReviewCriteriaRepository $criteriaRepository,
        EntityManagerInterface $em
    ): Response {
    
        if (!$this->getUser()) {
            // Stocker l'URL de retour dans la session
            $this->addFlash('redirect_after_login', $request->getUri());
            return $this->redirectToRoute('app_login');
        }
    
        if ($this->getUser()->getId() === $user->getId()) {
            $this->addFlash('error', 'Impossible');
            return $this->redirectToRoute('profil_talent', [
                'slug' => $user->getPersonalProfile()?->getSlug()
            ]);
        }
    
        $criteria = $criteriaRepository->findActiveByType('talent');
        
        if ($request->isMethod('POST')) {
    
            $comment = trim($request->request->get('comment'));
    
            if (empty($comment)) {
    
                $this->addFlash(
                    'error',
                    'Le commentaire est obligatoire'
                );
    
                return $this->redirectToRoute(
                    'review_create',
                    ['slug' => $user->getSlug()]
                );
            }
    
            $review = new Review();
            $review->setAuthor($this->getUser());
            $review->setTarget($user);
            $review->setComment($comment);
    
            $total = 0;
            $count = 0;
    
            foreach ($criteria as $criterion) {
    
                $value = (int)$request->request->get(
                    'criteria_'.$criterion->getId()
                );
    
                if ($value < 1 || $value > 10) {
                    continue;
                }
    
                $score = new ReviewScore();
                $score->setReview($review);
                $score->setCriteria($criterion);
                $score->setScore($value);
    
                $em->persist($score);
    
                $total += $value;
                $count++;
            }
    
            $review->setGlobalScore(
                round($total / max($count, 1), 1)
            );
    
            $em->persist($review);
            $em->flush();
    
            $this->addFlash(
                'success',
                'Votre avis a été enregistré avec succès.'
            );
    
            return $this->redirectToRoute(
                'profil_talent',
                [
                    'slug' => $user->getPersonalProfile()?->getSlug()
                ]
            );
        }
        
        return $this->render(
            'review/create.html.twig',
            [
                'target' => $user,
                'criteria' => $criteria
            ]
        );
    }
    
    
   
    #[Route('/clear-redirect-session', name: 'clear_redirect_session', methods: ['POST'])]
    public function clearRedirectSession(Request $request): Response
    {
        $request->getSession()->remove('_redirect_after_login');
        return new Response(null, 204);
    }
    
    #[Route('/store-redirect-url', name: 'store_redirect_url', methods: ['POST'])]
    public function storeRedirectUrl(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $redirectUrl = $data['redirect'] ?? null;
        
        if ($redirectUrl) {
            $request->getSession()->set('_redirect_after_login', $redirectUrl);
        }
        
        return new Response(null, 204);
    }

}
