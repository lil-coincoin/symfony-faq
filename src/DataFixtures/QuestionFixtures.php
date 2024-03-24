<?php

namespace App\DataFixtures;

use App\Entity\Question;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker;

class QuestionFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        /**
         * Il faut que les fixtures "User" soient générées avant de générer les questions
         */
        return [
            UserFixtures::class
        ];
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Faker\Factory::create();

        for ($i = 0; $i < 50; $i++) {
            // Choisi un numéro entre 0 et 49 correspond au nombre d'utilisateurs créés
            $number = $faker->numberBetween(0, 19);
            $user = $this->getReference("user-$number");

            $question = new Question();
            $question->setUser($user);
            $question->setTitre("{$faker->sentence} ?");
            $question->setContenu($faker->sentence);
            $question->setDateCreation($faker->dateTimeBetween('-2 years', '-6 months'));

            $manager->persist($question);

            $this->addReference("question-$i", $question);
        }

        $manager->flush();
    }
}
