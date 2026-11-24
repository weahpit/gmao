<?php

namespace App\EventSubscriber;

use App\Service\Image\ReducteurImage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * A la reception de toute requete contenant des fichiers, ramene chaque photo
 * (JPEG, PNG, WEBP) a 200 Ko maximum AVANT que le controleur ne la traite.
 *
 * Un seul point d'entree couvre donc tous les formulaires et l'API mobile :
 * photo employe, piece d'identite des visiteurs, messagerie, saisies de stock,
 * profil, etc. Les PDF, documents Office, audio et GIF ne sont pas touches.
 */
final class ReductionPhotosSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly ReducteurImage $reducteur)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['surRequete', 0]];
    }

    public function surRequete(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $requete = $event->getRequest();
        if (!in_array($requete->getMethod(), ['POST', 'PUT', 'PATCH'], true) || $requete->files->count() === 0) {
            return;
        }

        $fichiers = $this->parcourir($requete->files->all());
        $requete->files->replace($fichiers);
    }

    /**
     * @param array<mixed> $entree
     *
     * @return array<mixed>
     */
    private function parcourir(array $entree): array
    {
        foreach ($entree as $cle => $valeur) {
            // FileBag::replace() refuse null : un champ fichier facultatif vide
            // est converti en null par Symfony (UPLOAD_ERR_NO_FILE).
            if ($valeur === null) {
                unset($entree[$cle]);
            } elseif (is_array($valeur)) {
                $entree[$cle] = $this->parcourir($valeur);
            } elseif ($valeur instanceof UploadedFile) {
                $entree[$cle] = $this->traiter($valeur);
            }
        }

        return $entree;
    }

    private function traiter(UploadedFile $fichier): UploadedFile
    {
        if (!$fichier->isValid()) {
            return $fichier;
        }

        $chemin = $fichier->getPathname();
        if (!$this->reducteur->estReductible($chemin)) {
            return $fichier;
        }

        $resultat = $this->reducteur->reduire($chemin);
        if ($resultat === null) {
            return $fichier;
        }

        $base = pathinfo((string) $fichier->getClientOriginalName(), PATHINFO_FILENAME) ?: 'photo';

        return new UploadedFile(
            $resultat['chemin'],
            $base . '.' . $resultat['extension'],
            $resultat['mime'],
            \UPLOAD_ERR_OK,
            true
        );
    }
}
