<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\QuestionRepository;
use App\Repository\ReponseRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name:'app_admin')]
class AdminController extends AbstractController
{
    #[Route('', name:'')]
    public function users(UserRepository $userRepository): Response
    {
        //Recupere tous les utilisateurs
        $users = $userRepository->findBy([], ['nom' => 'ASC']);

        return $this->render('admin/users.html.twig', [
            'users' => $users
        ]);
    }

    #[Route('/user/{id}/role', name: '_change_role')]
    public function roleAdmin(User $user, EntityManagerInterface $entityManagerInterface, Request $request): Response
    {
        // Récupération des champs cachés dans le formulaire
        $token = $request->request->get('_token');

        // Vérifie si la méthode et le jeton reçu sont corrects
        if ($this->isCsrfTokenValid('admin_user-'. $user->getId(), $token)) {
            $user->setRoles(['ROLE_ADMIN']);
            $entityManagerInterface->persist($user);
            $entityManagerInterface->flush();
            $this->addFlash('success', "L'utilisateur {$user->getNom()} est maintenant un administrateur");
        } else {
            // Sinon, on génère un message d'erreur et on redirige l'utilisateur
            $this->addFlash('error', "Vous ne pouvez pas mettre {$user->getNom()} en administrateur");
        }

        return $this->redirectToRoute('app_admin');
    }

    #[Route('/reporting/questions', name: '_reporting_question')]
    public function reportingQuestions(QuestionRepository $questionRepository): Response{

        $questions = $questionRepository->getQuestionSixReponses();

        return $this->render('admin/reporting/question.html.twig', [
            'questions' => $questions
        ]);
    }

    #[Route('/reporting/reponses', name: '_reporting_reponse')]
    public function reportingReponses(ReponseRepository $reponseRepository): Response{

        $reponses = $reponseRepository->getReponseTenVote();

        return $this->render('admin/reporting/reponse.html.twig', [
            'reponses' => $reponses
        ]);
    }
}
