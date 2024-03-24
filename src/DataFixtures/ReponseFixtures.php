<?php

namespace App\DataFixtures;

use App\Entity\Reponse;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker;

class ReponseFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            QuestionFixtures::class
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Faker\Factory::create();

        for ($i = 0; $i < 500; $i++) {
            // Récupération d'un utilisateur aléatoire
            $user = $this->getReference("user-{$faker->numberBetween(0, 19)}");

            // Récupération d'une question aléatoire
            $question = $this->getReference("question-{$faker->numberBetween(0, 49)}");
            $dateCreationQuestion = $question->getDateCreation()->format('Y-m-d H:i:s');

            $reponse = new Reponse();
            $reponse->setUser($user);
            $reponse->setQuestion($question);
            $reponse->setContenu($faker->realText);
            $reponse->setDateCreation($faker->dateTimeBetween($dateCreationQuestion));

            //Ajout de votes à ma réponse
            for($j = 0; $j < $faker->numberBetween(0,15); $j++){
                //Recupere un utilisateur de manière aléatoire
                $user = $this->getReference("user-{$faker->numberBetween(0, 19)}");

                //Ajoute l'utilisateur à la collection
                $reponse->addVoter($user);
            }

            $manager->persist($reponse);
        }

        $manager->flush();
    }
}
