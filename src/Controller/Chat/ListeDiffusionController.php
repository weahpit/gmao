<?php

namespace App\Controller\Chat;

use App\Entity\Chat\ListeDiffusion;
use App\Entity\Groupe;
use App\Entity\User;
use App\Repository\Chat\ListeDiffusionRepository;
use App\Repository\Chat\MessageChatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Administration des listes de diffusion de la messagerie.
 *
 * Une liste rassemble des groupes d'utilisateurs et/ou des personnes
 * designees individuellement. Elle devient un canal de discussion ou tous
 * ses membres peuvent ecrire et lire.
 */
#[Route('/messagerie/listes')]
#[IsGranted('ROLE_ADMIN')]
final class ListeDiffusionController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private ListeDiffusionRepository $listes,
        private MessageChatRepository $messages,
    ) {}

    #[Route('', name: 'app_chat_listes', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $aModifier = null;
        $identifiant = (string) $request->query->get('modifier', '');
        if ($identifiant !== '') {
            try {
                $trouvee = $this->listes->find(Uuid::fromString($identifiant));
                if ($trouvee instanceof ListeDiffusion) {
                    $aModifier = $trouvee;
                }
            } catch (\Throwable) {
                // identifiant invalide : on reste en creation
            }
        }

        $toutes = $this->listes->toutesTriees();
        $statistiques = [];
        foreach ($toutes as $liste) {
            $statistiques[(string) $liste->getId()] = $this->messages->compterListe($liste);
        }

        return $this->render('chat/listes.html.twig', [
            'listes'       => $toutes,
            'aModifier'    => $aModifier,
            'statistiques' => $statistiques,
            'groupes'      => $this->em->getRepository(Groupe::class)->findBy([], ['nom_groupe' => 'ASC']),
            'utilisateurs' => $this->em->getRepository(User::class)->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route('/enregistrer', name: 'app_chat_listes_enregistrer', methods: ['POST'])]
    public function enregistrer(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('chat_liste', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Rechargez la page et réessayez.');

            return $this->redirectToRoute('app_chat_listes');
        }

        $nom = trim((string) $request->request->get('nom', ''));
        if ($nom === '') {
            $this->addFlash('danger', 'Le nom de la liste est obligatoire.');

            return $this->redirectToRoute('app_chat_listes');
        }

        $liste = null;
        $identifiant = (string) $request->request->get('id', '');
        if ($identifiant !== '') {
            try {
                $liste = $this->listes->find(Uuid::fromString($identifiant));
            } catch (\Throwable) {
                $liste = null;
            }
        }
        if (!$liste instanceof ListeDiffusion) {
            $liste = new ListeDiffusion();
            $liste->setCreePar($this->getUser() instanceof User ? $this->getUser() : null);
            $this->em->persist($liste);
        }

        // Unicite du nom (comparaison insensible a la casse).
        foreach ($this->listes->findAll() as $autre) {
            if ($autre !== $liste && mb_strtolower((string) $autre->getNom()) === mb_strtolower($nom)) {
                $this->addFlash('danger', 'Une liste nommée « ' . $nom . ' » existe déjà. Choisissez un autre nom.');

                return $this->redirectToRoute('app_chat_listes');
            }
        }

        $liste->setNom(mb_substr($nom, 0, 120))
            ->setDescription(trim((string) $request->request->get('description', '')) ?: null)
            ->setActive((bool) $request->request->get('active', true));

        // --- Composition -------------------------------------------------
        foreach ($liste->getGroupes()->toArray() as $groupe) {
            $liste->removeGroupe($groupe);
        }
        foreach ((array) $request->request->all('groupes') as $idGroupe) {
            try {
                $groupe = $this->em->getRepository(Groupe::class)->find(Uuid::fromString((string) $idGroupe));
            } catch (\Throwable) {
                $groupe = null;
            }
            if ($groupe instanceof Groupe) {
                $liste->addGroupe($groupe);
            }
        }

        foreach ($liste->getMembres()->toArray() as $membre) {
            $liste->removeMembre($membre);
        }
        foreach ((array) $request->request->all('membres') as $idMembre) {
            try {
                $membre = $this->em->getRepository(User::class)->find(Uuid::fromString((string) $idMembre));
            } catch (\Throwable) {
                $membre = null;
            }
            if ($membre instanceof User) {
                $liste->addMembre($membre);
            }
        }

        $this->em->flush();

        $effectif = count($liste->membresEffectifs());
        $this->addFlash('success', sprintf(
            'Liste « %s » enregistrée : %d membre(s) au total.',
            $liste->getNom(),
            $effectif
        ));

        return $this->redirectToRoute('app_chat_listes');
    }

    #[Route('/{id}/basculer', name: 'app_chat_listes_basculer', methods: ['POST'])]
    public function basculer(string $id, Request $request): Response
    {
        $liste = $this->trouver($id);
        if ($liste === null) {
            return $this->redirectToRoute('app_chat_listes');
        }
        if (!$this->isCsrfTokenValid('chat_liste_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_chat_listes');
        }

        $liste->setActive(!$liste->isActive());
        $this->em->flush();

        $this->addFlash('success', sprintf('Liste « %s » %s.', $liste->getNom(), $liste->isActive() ? 'réactivée' : 'désactivée'));

        return $this->redirectToRoute('app_chat_listes');
    }

    #[Route('/{id}/supprimer', name: 'app_chat_listes_supprimer', methods: ['POST'])]
    public function supprimer(string $id, Request $request): Response
    {
        $liste = $this->trouver($id);
        if ($liste === null) {
            return $this->redirectToRoute('app_chat_listes');
        }
        if (!$this->isCsrfTokenValid('chat_liste_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('app_chat_listes');
        }

        $nom = $liste->getNom();
        $this->em->remove($liste);
        $this->em->flush();

        $this->addFlash('success', 'Liste « ' . $nom . ' » supprimée (les messages qu\'elle contenait sont retirés).');

        return $this->redirectToRoute('app_chat_listes');
    }

    private function trouver(string $id): ?ListeDiffusion
    {
        try {
            $liste = $this->listes->find(Uuid::fromString($id));
        } catch (\Throwable) {
            return null;
        }

        return $liste instanceof ListeDiffusion ? $liste : null;
    }
}
