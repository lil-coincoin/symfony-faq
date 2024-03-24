<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Instance de Faker
        $faker = Faker\Factory::create();

        // Création de 50 utilisateurs
        for ($i = 0; $i < 20; $i++) {
            $user = new User();
            $user->setPassword($this->passwordHasher->hashPassword($user, 'secret'));
            $user->setEmail($faker->unique()->email);
            $user->setNom($faker->name);
            $user->setIsVerified($faker->boolean);

            // Persiste les données
            $manager->persist($user);

            // Enregistre l'objet $user dans une référence avec un nom unique !
            $this->addReference("user-$i", $user);
        }

        //Création d'un administrateur de test
        $admin = new User();
        $admin->setPassword($this->passwordHasher->hashPassword($user, 'secret'));
        $admin->setNom('John Doe');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setEmail('demo@demo.fr');
        $admin->setIsVerified(true);

        $manager->persist($admin);

        // Met à jour les modifications en BDD
        $manager->flush();
    }
}
