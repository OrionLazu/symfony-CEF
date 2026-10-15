<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Enregistre les images envoyées depuis le back-office dans public/images/products.
 */
class ProductImageUploader
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public/images/products')]
        private readonly string $targetDirectory,
        private readonly SluggerInterface $slugger,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * Déplace le fichier envoyé et retourne son nouveau nom.
     *
     * Le nom d'origine est « slugifié » et suffixé d'un identifiant unique :
     * on n'utilise jamais tel quel un nom de fichier fourni par l'utilisateur.
     */
    public function upload(UploadedFile $file): string
    {
        $baseName = $this->slugger->slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))->lower();
        // L'extension est déduite du contenu réel du fichier, pas de son nom.
        $fileName = sprintf('%s-%s.%s', $baseName ?: 'sweat', bin2hex(random_bytes(6)), $file->guessExtension() ?? 'jpg');

        $file->move($this->targetDirectory, $fileName);

        return $fileName;
    }

    /**
     * Supprime une image envoyée précédemment (les images d'origine 1.jpeg à 10.jpeg sont conservées).
     */
    public function remove(?string $fileName): void
    {
        if (null === $fileName || preg_match('/^\d+\.jpeg$/', $fileName)) {
            return;
        }

        $path = $this->targetDirectory.'/'.basename($fileName);
        if ($this->filesystem->exists($path)) {
            $this->filesystem->remove($path);
        }
    }
}
