<?php

namespace App\Security\Voter;

use App\Repository\ReponseRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class ReponseVoter extends Voter
{
    public const EDIT = 'REPONSE_EDIT';
    public const VIEW = 'REPONSE_VIEW';
    public const DELETE = 'REPONSE_DELETE';
    public const VOTE = 'REPONSE_VOTE';

    public function __construct(
        private ReponseRepository $reponseRepository,
        private Security $security
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::VIEW, self::DELETE, self::VOTE])
            && $subject instanceof \App\Entity\Reponse;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        // if the user is anonymous, do not grant access
        if (!$user instanceof UserInterface) {
            return false;
        }

        // ... (check conditions and return true to grant permission) ...
        switch ($attribute) {
            case self::DELETE:
                return $subject->getUser() === $user || $this->security->isGranted('ROLE_ADMIN');
                break;
            case self::VIEW:
            case self::EDIT:
                /**
                 * Si l'auteur du sujet (donc la réponse) est égal à l'utilisateur connecté
                 * alors, on autorise la modification de la réponse
                 */
                return $subject->getUser() === $user;
                break;
            case self::VOTE:
                $hasVoted = $this->reponseRepository->hasVoted($user, $subject->getQuestion());
                return $hasVoted === false && $subject->getUser() !== $user;
        }

        return false;
    }
}