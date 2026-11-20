<?php

namespace App\Security;

use App\Entity\Rh\Absence;
use App\Entity\Rh\Employe;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Regles d'acces du module RH.
 *
 * Le projet ne charge pas de security.yaml : la hierarchie de roles n'est pas
 * declaree. On lit donc directement le tableau de roles de l'utilisateur et on
 * considere ROLE_ADMIN comme super-administrateur.
 *
 * Roles attendus (a declarer dans la gestion des utilisateurs) :
 *   ROLE_DRH          directeur des ressources humaines
 *   ROLE_RH           gestionnaire RH (dossiers, contrats, conges)
 *   ROLE_MEDECIN      medecin du travail
 *   ROLE_INFIRMIER    infirmier(ere)
 *   ROLE_CHEF_SERVICE chef de service (valide les conges de son service)
 *   ROLE_MANAGER      responsable d'equipe (evaluations)
 */
final class RhAcces
{
    public const ROLES_RH = ['ROLE_DRH', 'ROLE_RH'];
    public const ROLES_MEDICAUX = ['ROLE_MEDECIN', 'ROLE_INFIRMIER'];
    public const ROLES_SALAIRES = ['ROLE_DRH', 'ROLE_RH'];

    public function __construct(private Security $security) {}

    public function utilisateur(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    /** @return list<string> */
    public function roles(): array
    {
        return $this->utilisateur()?->getRoles() ?? [];
    }

    /** Vrai si l'utilisateur possede au moins un des roles demandes (ROLE_ADMIN passe partout). */
    public function aRole(string ...$roles): bool
    {
        $miens = $this->roles();
        if (in_array('ROLE_ADMIN', $miens, true)) {
            return true;
        }

        return (bool) array_intersect($roles, $miens);
    }

    public function estRh(): bool
    {
        return $this->aRole(...self::ROLES_RH);
    }

    /** Donnees medicales : medecin, infirmier ou administrateur, jamais le RH administratif. */
    public function peutVoirMedical(): bool
    {
        return $this->aRole(...self::ROLES_MEDICAUX);
    }

    /** Salaires et masse salariale : RH et DRH seulement. */
    public function peutVoirSalaires(): bool
    {
        return $this->aRole(...self::ROLES_SALAIRES);
    }

    public function peutRecruter(): bool
    {
        return $this->aRole('ROLE_DRH', 'ROLE_RH');
    }

    public function peutEvaluer(): bool
    {
        return $this->aRole('ROLE_DRH', 'ROLE_RH', 'ROLE_MANAGER', 'ROLE_CHEF_SERVICE');
    }

    /**
     * Validation de conges : le responsable direct, le chef du meme service,
     * ou le service RH.
     */
    public function peutValiderConge(Absence $absence): bool
    {
        if ($this->estRh()) {
            return true;
        }

        $user = $this->utilisateur();
        $employe = $absence->getEmploye();
        if ($user === null || $employe === null) {
            return false;
        }

        $responsable = $employe->getManager()?->getUtilisateur();
        if ($responsable !== null && $responsable->getId() === $user->getId()) {
            return true;
        }

        if ($this->aRole('ROLE_CHEF_SERVICE')) {
            $serviceEmploye = $employe->getService();
            $serviceUser = $user->getCodeService();
            if ($serviceEmploye !== null && $serviceUser !== null && $serviceEmploye->getId() === $serviceUser->getId()) {
                return true;
            }
        }

        return false;
    }

    /** Un employe (ou son responsable) peut consulter sa propre fiche. */
    public function peutVoirEmploye(Employe $employe): bool
    {
        if ($this->estRh() || $this->peutVoirMedical()) {
            return true;
        }

        $user = $this->utilisateur();
        if ($user === null) {
            return false;
        }

        if ($employe->getUtilisateur()?->getId() === $user->getId()) {
            return true;
        }

        return $this->aRole('ROLE_CHEF_SERVICE')
            && $employe->getService() !== null
            && $employe->getService()?->getId() === $user->getCodeService()?->getId();
    }

    /** @throws AccessDeniedException */
    public function exigerMedical(): void
    {
        if (!$this->peutVoirMedical()) {
            throw new AccessDeniedException('Cette section contient des donnees medicales reservees au service de sante au travail.');
        }
    }

    /** @throws AccessDeniedException */
    public function exigerRh(): void
    {
        if (!$this->estRh()) {
            throw new AccessDeniedException('Cette action est reservee au service des ressources humaines.');
        }
    }
}
