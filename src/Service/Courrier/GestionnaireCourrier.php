<?php

namespace App\Service\Courrier;

use App\Entity\Courrier\Courrier;
use App\Entity\Courrier\PieceJointeCourrier;
use App\Repository\Courrier\CourrierRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Regles metier du courrier : numerotation, depot des pieces jointes,
 * suppression.
 *
 * Numerotation :
 *   ENT-AAAA-NNNN pour un courrier entrant,
 *   SOR-AAAA-NNNN pour un courrier sortant,
 * la sequence repartant a 0001 chaque annee. Le numero est attribue a
 * l'enregistrement et n'est plus modifie ensuite.
 */
final class GestionnaireCourrier
{
    public function __construct(
        private EntityManagerInterface $em,
        private CourrierRepository $courriers,
        private StockagePiecesJointes $stockage,
        private LoggerInterface $logger,
    ) {}

    /** Attribue le prochain numero d'enregistrement au courrier. */
    public function attribuerNumero(Courrier $courrier): string
    {
        $annee = ($courrier->getDateCourrier() ?? new \DateTimeImmutable())->format('Y');
        $prefixe = ($courrier->isEntrant() ? 'ENT-' : 'SOR-') . $annee . '-';

        // Verrou consultatif PostgreSQL : deux enregistrements simultanes ne
        // peuvent pas obtenir le meme numero de sequence.
        try {
            $this->em->getConnection()->executeStatement(
                'SELECT pg_advisory_xact_lock(hashtext(:cle))',
                ['cle' => 'courrier-' . $prefixe]
            );
        } catch (\Throwable) {
            // Base non PostgreSQL : on continue sans verrou.
        }

        $dernier = $this->courriers->dernierNumero($prefixe);
        $suivant = 1;

        if (is_string($dernier) && preg_match('/-(\d+)$/', $dernier, $m)) {
            $suivant = (int) $m[1] + 1;
        }

        $numero = $prefixe . str_pad((string) $suivant, 4, '0', STR_PAD_LEFT);

        // Filet de securite : si la lecture du dernier numero a echoue, on
        // cherche le premier numero libre pour ne jamais enregistrer un
        // courrier sans numero.
        try {
            while ($this->courriers->findOneBy(['numero' => $numero]) !== null) {
                $suivant++;
                $numero = $prefixe . str_pad((string) $suivant, 4, '0', STR_PAD_LEFT);
            }
        } catch (\Throwable) {
            // Base indisponible pour ce controle : le numero calcule est garde.
        }

        $courrier->setNumero($numero);

        return $numero;
    }

    /**
     * Valide et enregistre une piece jointe deposee.
     *
     * @return string|null message d'erreur, null si tout va bien
     */
    public function ajouterPieceJointe(Courrier $courrier, UploadedFile $fichier, ?string $commentaire = null): ?string
    {
        // 1) Les erreurs de transfert PHP sont traitees AVANT tout appel a
        //    Symfony : selon la version, isValid() ne repond pas exactement
        //    la meme chose pour un fichier n'ayant pas ete envoye.
        if ((int) $fichier->getError() !== UPLOAD_ERR_OK) {
            return $this->messageErreurUpload($fichier);
        }

        // 2) Fichier bien recu, mais depose dans le dossier temporaire sans
        //    chemin exploitable (dossier temporaire absent / non inscriptible).
        try {
            $chemin = $fichier->getPathname();
        } catch (\Throwable) {
            $chemin = null;
        }
        if (!is_string($chemin) || $chemin === '' || !is_file($chemin)) {
            return 'Le fichier « ' . $fichier->getClientOriginalName() . ' » n\'a pas ete recu entierement par le serveur : '
                . 'le dossier temporaire de PHP (upload_tmp_dir) est peut-etre plein, absent ou non inscriptible. '
                . 'Verifiez aussi les limites upload_max_filesize (' . ini_get('upload_max_filesize') . ') et '
                . 'post_max_size (' . ini_get('post_max_size') . ').';
        }

        $extension = strtolower((string) pathinfo($fichier->getClientOriginalName(), PATHINFO_EXTENSION));
        if (!in_array($extension, PieceJointeCourrier::EXTENSIONS, true)) {
            return 'Le fichier « ' . $fichier->getClientOriginalName() . ' » n\'est pas d\'un format accepte (formats autorises : '
                . implode(', ', PieceJointeCourrier::EXTENSIONS) . ').';
        }

        if ((int) $fichier->getSize() > PieceJointeCourrier::TAILLE_MAX) {
            return 'Le fichier « ' . $fichier->getClientOriginalName() . ' » depasse 20 Mo.';
        }

        $piece = (new PieceJointeCourrier())->setCommentaire($commentaire !== null && trim($commentaire) !== '' ? trim($commentaire) : null);

        $erreurDossier = $this->stockage->preparerDossier();
        if ($erreurDossier !== null) {
            $this->logger->error('Dossier des pieces jointes inutilisable', ['cause' => $erreurDossier]);

            return 'Le fichier « ' . $fichier->getClientOriginalName() . ' » n\'a pas pu etre enregistre : ' . $erreurDossier;
        }

        try {
            $this->stockage->stocker($fichier, $piece);
        } catch (\Throwable $e) {
            $this->logger->error('Echec du stockage d une piece jointe de courrier', [
                'fichier' => $fichier->getClientOriginalName(),
                'cause' => $e->getMessage(),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
            ]);

            return 'Le fichier « ' . $fichier->getClientOriginalName() . ' » n\'a pas pu etre enregistre : '
                . $e->getMessage() . ' (detail technique dans le journal du site).';
        }

        $courrier->addPieceJointe($piece);

        return null;
    }

    /** Supprime une piece jointe (fichier + fiche). */
    public function supprimerPieceJointe(Courrier $courrier, PieceJointeCourrier $piece): void
    {
        $this->stockage->supprimer($piece);
        $courrier->removePieceJointe($piece);
    }

    /** Supprime le courrier, ses pieces jointes et leurs fichiers. */
    public function supprimer(Courrier $courrier): void
    {
        foreach ($courrier->getPiecesJointes()->toArray() as $piece) {
            $this->stockage->supprimer($piece);
            $courrier->removePieceJointe($piece);
        }

        $this->em->remove($courrier);
        $this->em->flush();
    }

    private function messageErreurUpload(UploadedFile $fichier): string
    {
        $nom = '« ' . $fichier->getClientOriginalName() . ' » : ';

        return $nom . match ($fichier->getError()) {
            UPLOAD_ERR_INI_SIZE => 'le fichier depasse la taille maximale autorisee par le serveur PHP (upload_max_filesize = '
                . ini_get('upload_max_filesize') . ' ; prevoyez 20 Mo avec un post_max_size de 64 Mo).',
            UPLOAD_ERR_FORM_SIZE => 'le fichier depasse la taille maximale acceptee par le formulaire.',
            UPLOAD_ERR_PARTIAL => 'le transfert du fichier a ete interrompu, merci de reessayer.',
            UPLOAD_ERR_NO_FILE => 'aucun fichier recu.',
            UPLOAD_ERR_NO_TMP_DIR => 'le dossier temporaire du serveur est absent : a signaler a l administrateur.',
            UPLOAD_ERR_CANT_WRITE => 'le serveur n a pas pu ecrire le fichier sur son disque (droits du dossier temporaire).',
            UPLOAD_ERR_EXTENSION => 'l envoi a ete interrompu par une extension PHP du serveur.',
            default => 'le transfert a echoue (erreur ' . $fichier->getError() . ').',
        };
    }
}
