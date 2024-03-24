<?php

namespace App\Controller;

use App\Entity\Question;
use App\Form\ReponseFormType;
use App\Repository\QuestionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Reponse;
use App\Entity\User;
use App\Form\QuestionFormType;
use App\Repository\ReponseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class QuestionController extends AbstractController
{
    public function __construct(
        private QuestionRepository $questionRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Accueil - Affichage de toutes les questions utilisateurs
     */
    #[Route('/', name: 'app_question')]
    public function index(): Response
    {
        // Sélectionne toutes les questions ordré par date (plus récent au plus vieux)
        $questions = $this->questionRepository->findBy([], ['dateCreation' => 'DESC']);

        return $this->render('question/index.html.twig', [
            'questions' => $questions
        ]);
    }

    /**
     * Affiche la question ainsi que toutes ses réponses
     */
    #[Route('/question/{id}', name: 'app_question_reponses', requirements: ['id' => '\d+'])]
    public function responses(Question $question, Request $request, MailerInterface $mailer, ReponseRepository $reponseRepository): Response
    {
        //Gestion des réponses
        $response = new Reponse();
        $form = $this->createForm(ReponseFormType::class, $response);

        //Clone du formulaire vide dans un nouvel objet
        $emptyForm =  clone $form;

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
            $response->setDateCreation(new \DateTime());
            $response->setQuestion($question);
            $response->setUser($this->getUser());

            $this->entityManager->persist($response);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre commentaire à bien été ajouté');

            //Ne pas envoyer d'email si la réponse est posté par l'auteur de la question
            if($response->getUser() !== $question->getUser()){
                //Envoyez un email à l'auteur de la question
                $email = (new TemplatedEmail())
                    ->to(new Address($question->getUser()->getEmail(), $question->getUser()->getNom()))
                    ->from(new Address('noreply@faq.com', 'FAQ'))
                    ->subject('Bonne nouvelle !')
                    ->htmlTemplate('emails/new_reponse.html.twig')
                    ->context([
                        'name' => $question->getUser()->getNom(),
                        'question' => $question->getTitre(),
                        'url' => $this->generateUrl(
                            'app_question_reponses',
                            ['id' => $question->getId()],
                            UrlGeneratorInterface::ABSOLUTE_URL
                        )
                    ])
                ;

                $mailer->send($email);
            }
            //On reclone notre objet formulaire vide dans l'objet de départ
            $form = clone $emptyForm;
        }

        return $this->render('question/reponses.html.twig', [
            'question' => $question,
            'formResponse' => $form,
        ], new Response(null, $form->isSubmitted() && !$form->isValid() ? 420 : 200));
    }

    /**
     * Ajouter une nouvelle question
     */
    #[IsGranted('QUESTION_ADD', null, 'Ouvrez un compte ou connectez-vous pour poser une question')]
    #[Route('/question/new', name: 'app_question_new')]
    public function addQuestion(Request $request): Response
    {
        $question = new Question();
        $form = $this->createForm(QuestionFormType::class, $question);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $question->setUser($this->getUser());
            $question->setDateCreation(new \DateTime());

            $this->entityManager->persist($question);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre question est désormais ouverte au public 🥳');

            return $this->redirectToRoute('app_question_reponses', [
                'id' => $question->getId()
            ]);
        }

        return $this->render('question/new.html.twig', [
            'formNewQuestion' => $form
        ]);
    }

    /**
     * Permet à un utilisateur de modifier sa réponse
     */
    #[IsGranted('REPONSE_EDIT', 'reponse', 'Vous ne pouvez pas éditer cette réponse')]
    #[Route('/reponse/{id}/edit', name: 'app_reponse_edit')]
    public function editReponse(Reponse $reponse, Request $request): Response
    {
        // if(!$this->isGranted('REPONSE_EDIT', $reponse)){
        //     throw $this->createAccessDeniedException("Vous ne pouvez pas modifier cette réponse");
        // }

        $form = $this->createForm(ReponseFormType::class, $reponse);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){

            //Date de modification
            $reponse->setDateModification(new \DateTime());

            $this->entityManager->persist($reponse);
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Votre réponse a bien été modifié');

            return $this->redirectToRoute('app_question_reponses', [
                'id' => $reponse->getQuestion()->getId()
            ]);
        }

        return $this->render('question/newResponse.html.twig', [
            'formEditResponse' => $form
        ]);
    }


    /**
     * Supprimer une question
     */
    #[IsGranted('QUESTION_DELETE', 'question', 'Vous ne pouvez pas supprimer une question ne vous appartenant pas')]
    #[Route('/question/{id}/delete', name: 'app_question_delete', requirements: ['id' => '\d+'])]
    public function deleteQuestion(Question $question, Request $request): RedirectResponse
    {
        // Récupération des champs cachés dans le formulaire
        $token = $request->request->get('_token');
        $method = $request->request->get('_method');

        // Vérifie si la méthode et le jeton reçu sont corrects
        if ($method === 'DELETE' && $this->isCsrfTokenValid('question_delete', $token)) {
            // Effectue la suppression
            $this->entityManager->remove($question);
            $this->entityManager->flush();

            // Génération d'un message de succès et redirection vers l'accueil
            $this->addFlash('success', 'Votre question à bien été supprimée');

            return $this->redirectToRoute('app_question');
        }

        // Sinon, on génère un message d'erreur et on redirige l'utilisateur
        // vers le détail de la question
        $this->addFlash('error', 'Vous ne pouvez pas supprimer cette question');

        return $this->redirectToRoute('app_question_reponses', [
            'id' => $question->getId()
        ]);
    }

    /**
     * Modification d'une question
     */
    #[IsGranted('QUESTION_EDIT', 'question', 'Vous ne pouvez pas modifier cette question')]
    #[Route('/question/{id}/edit', name: 'app_question_edit', requirements: ['id' => '\d+'])]
    public function editQuestion(Question $question, Request $request): Response
    {
        $form = $this->createForm(QuestionFormType::class, $question, [
            'labelButton' => 'Modifier ma réponse'
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($question);
            $this->entityManager->flush();

            $this->addFlash('success', 'Vos modifications ont bien été enregistrées');
        }

        return $this->render('question/edit.html.twig', [
            'formEditQuestion' => $form
        ]);
    }

    /**
     * Supprimer une réponse
     */
    #[IsGranted('REPONSE_DELETE', 'reponse', 'Vous ne pouvez pas supprimer une réponse ne vous appartenant pas')]
    #[Route('/reponse/{id}/delete', name: 'app_reponse_delete', requirements: ['id' => '\d+'])]
    public function deleteReponse(Reponse $reponse, Request $request): RedirectResponse
    {
        // Récupération des champs cachés dans le formulaire
        $token = $request->request->get('_token');
        $method = $request->request->get('_method');

        // Vérifie si la méthode et le jeton reçu sont corrects
        if ($method === 'DELETE' && $this->isCsrfTokenValid('reponse_delete-'. $reponse->getId(), $token)) {
            // Effectue la suppression
            $this->entityManager->remove($reponse);
            $this->entityManager->flush();

            // Génération d'un message de succès et redirection vers l'accueil
            $this->addFlash('success', 'Votre réponse à bien été supprimée');
        } else {
            // Sinon, on génère un message d'erreur et on redirige l'utilisateur
            // vers le détail de la question
            $this->addFlash('error', 'Vous ne pouvez pas supprimer cette réponse');
        }

        return $this->redirectToRoute('app_question_reponses', [
            'id' => $reponse->getQuestion()->getId()
        ]);
    }

    /**
     * Permet à un utilisateur de voter
     */
    #[IsGranted('REPONSE_VOTE', 'reponse', 'Vous avez déja voté')]
    #[Route('/reponse/{id}/vote', name: 'app_reponse_vote')]
    public function vote(Reponse $reponse, Request $request): RedirectResponse{

        $token = $request->request->get('_token');

        if ($request->getMethod() === 'POST' && $this->isCsrfTokenValid('vote-'. $reponse->getId(), $token)) {
            /** @var User $user */
            $user = $this->getUser();

            // Associe la réponse à l'utilisateur
            $user->addVote($reponse);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'Merci pour votre vote !');
        } else {
            $this->addFlash('error', 'Vous ne pouvez plus voter ici !');
        }

        return $this->redirectToRoute('app_question_reponses', [
            'id' => $reponse->getQuestion()->getId()
        ]);
    }

    /**
     * Permet à un utilisateur de signaler une question
     */
    #[IsGranted('USER_ACCESS')]
    #[Route('/{type}/{id}/signaler', name: 'app_question_signaler', requirements: ['id' => '\d+', 'type' => 'question|reponse'])]
    public function signaler(string $type, int $id, MailerInterface $mailer, Request $request, ReponseRepository $reponseRepository): RedirectResponse
    {
        if ($type === 'question') {
            $question = $this->questionRepository->find($id);

            // Erreur 404
            if (!$question) {
                throw $this->createNotFoundException('Aucune question sous cet ID');
            }

            $questionId = $question->getId();
        } else {
            $reponse = $reponseRepository->find($id);

            // Erreur 404
            if (!$reponse) {
                throw $this->createNotFoundException('Aucune réponse sous cet ID');
            }

            $questionId = $reponse->getQuestion()->getId();
        }

        // Gérer $questionId sur le token

        $token = $request->request->get('_token');

        if ($this->isCsrfTokenValid("report-$type-$id", $token)) {
            /** @var User $user */
            $user = $this->getUser();

            $email = (new TemplatedEmail())
                ->from(new Address($user->getEmail(), $user->getNom()))
                ->to('report@faq.test')
                ->subject('Signalement FAQ')
                ->htmlTemplate('emails/report.html.twig')
                ->context([
                    'type' => $type,
                    'nom' => $user->getNom(),
                    'url' => $this->generateUrl(
                        'app_question_reponses',
                        ['id' => $questionId],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    )
                ]);

            $mailer->send($email);

            $this->addFlash('success', "Votre signalement à bien été transmis ! Merci d'avoir balancé");
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide');
        }

        return $this->redirectToRoute('app_question_reponses', [
            'id' => $questionId
        ]);
    }
}
