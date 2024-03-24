<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Permet d'effectuer un upload
 */
class UploadService{

    public function upload(UploadedFile $file, string $oldFile = null): string{
        // Récupère le nom original du fichier envoyé
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        // "Sluggify" le nom du fichier
        $slugger = new AsciiSlugger();
        $safeFilename = $slugger->slug($originalFilename);
        $uniqid = uniqid('', true);

        // Nouveau nom du fichier : nom_original-1254858.png
        $newFilename = "$safeFilename-$uniqid.{$file->guessExtension()}";

        // Upload dans le dossier "avatars"
        $file->move('avatars',$newFilename);

        //Instancie le composant Symfony Filesystem
        $filesystem = new Filesystem();

        //Si l'argument $oldFile est différent de null et que le fichier existe
        if($oldFile !== null && $oldFile !== 'imgs/user_default.jpg' && $filesystem->exists($oldFile)){
            //Alors on supprime celui-ci
            $filesystem->remove($oldFile);
        }

        return $newFilename;
    }
}
?>