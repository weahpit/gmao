<?php

namespace App\Service\Chat;

use App\Entity\Chat\ListeDiffusion;
use App\Entity\Chat\MessageChat;
use App\Entity\User;
use App\Repository\Chat\LectureChatRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Accusé de réception et de lecture des messages.
 *
 * Trois états, du plus faible au plus fort :
 *
 *   envoye : le message est enregistré sur le serveur (l'expéditeur a recu
 *            l'identifiant du message). Aucun destinataire ne l'a encore ouvert ;
 *   remis  : l'application d'au moins un destinataire a récupéré le message ;
 *   lu     : au moins un destinataire a ouvert la conversation après l'envoi.
 *
 * Ce service centralise le calcul pour la messagerie web et pour l'API
 * mobile, afin que les deux affichent exactement la même information.
 */
final class AccuseLecture
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LectureChatRepository $lectures,
    ) {
    }

    /**
     * État de chaque message pour son expéditeur.
     *
     * @param MessageChat[] $messages            messages dont on veut l'état
     * @param string        $canal               identifiant du canal (general, liste:<uuid>, uuid)
     * @param string        $typeCanal           general, liste ou prive
     * @param int           $totalDestinataires  nombre de personnes censées recevoir le message
     *
     * @return array<string, array{statut: string, lu_par: int, total: int, lu_at: ?string, remis_at: ?string}>
     */
    public function etats(array $messages, string $canal, string $typeCanal, int $totalDestinataires): array
    {
        $etats = [];
        if ($messages === []) {
            return $etats;
        }

        // --- Conversation privée : le drapeau du message suffit -----------
        if ($typeCanal === 'prive') {
            foreach ($messages as $message) {
                $etats[(string) $message->getId()] = [
                    'statut'   => $message->statut(),
                    'lu_par'   => $message->isLu() ? 1 : 0,
                    'total'    => 1,
                    'lu_at'    => $message->getLuAt()?->format('Y-m-d H:i:s'),
                    'remis_at' => $message->getRemisAt()?->format('Y-m-d H:i:s'),
                ];
            }

            return $etats;
        }

        // --- Canal de groupe : on compare aux repères de lecture ----------
        $lecteurs = $this->lectures->parCanal($canal);

        foreach ($messages as $message) {
            $auteur = $message->getExpediteur();
            $auteurId = $auteur instanceof User ? (string) $auteur->getId() : '';
            $envoi = $message->getCreatedAt();

            $luPar = 0;
            $derniereLecture = null;
            if ($envoi !== null) {
                foreach ($lecteurs as $lecture) {
                    $utilisateur = $lecture->getUtilisateur();
                    if (!$utilisateur instanceof User) {
                        continue;
                    }
                    if ((string) $utilisateur->getId() === $auteurId) {
                        continue; // l'auteur ne compte pas comme lecteur
                    }
                    $quand = $lecture->getDernierLuAt();
                    if ($quand === null || $quand < $envoi) {
                        continue;
                    }
                    ++$luPar;
                    if ($derniereLecture === null || $quand > $derniereLecture) {
                        $derniereLecture = $quand;
                    }
                }
            }

            $etats[(string) $message->getId()] = [
                'statut'   => $luPar > 0 ? 'lu' : ($message->getRemisAt() !== null ? 'remis' : 'envoye'),
                'lu_par'   => $luPar,
                'total'    => max(0, $totalDestinataires),
                'lu_at'    => $derniereLecture?->format('Y-m-d H:i:s'),
                'remis_at' => $message->getRemisAt()?->format('Y-m-d H:i:s'),
            ];
        }

        return $etats;
    }

    /**
     * Marque comme « remis » les messages que ce lecteur vient de récupérer.
     *
     * Appelé quand un destinataire ouvre le canal ou interroge les nouveaux
     * messages : à partir de cet instant, l'expéditeur voit deux coches.
     *
     * @param MessageChat[] $messages messages récupérés par le lecteur
     *
     * @return int nombre de messages effectivement mis à jour
     */
    public function marquerRemis(array $messages, User $lecteur): int
    {
        $lecteurId = (string) $lecteur->getId();
        $maintenant = new \DateTimeImmutable();
        $modifies = 0;

        foreach ($messages as $message) {
            $auteur = $message->getExpediteur();
            if ($auteur instanceof User && (string) $auteur->getId() === $lecteurId) {
                continue; // on ne « remet » pas son propre message
            }
            if ($message->getRemisAt() !== null) {
                continue;
            }
            $message->setRemisAt($maintenant);
            ++$modifies;
        }

        if ($modifies > 0) {
            $this->em->flush();
        }

        return $modifies;
    }

    /**
     * Nombre de personnes censées recevoir un message du canal.
     *
     * Sert uniquement à écrire « Lu par 3 / 12 » ; jamais à autoriser quoi
     * que ce soit.
     */
    public function totalDestinataires(string $typeCanal, ?ListeDiffusion $liste, ?User $auteur): int
    {
        if ($typeCanal === 'prive') {
            return 1;
        }

        if ($typeCanal === 'liste' && $liste !== null) {
            $membres = count($liste->membresEffectifs());
            if ($auteur !== null && $liste->contient($auteur)) {
                --$membres; // l'auteur ne se lit pas lui-même
            }

            return max(0, $membres);
        }

        $total = (int) $this->em->getRepository(User::class)->count([]);

        return max(0, $total - 1);
    }
}
