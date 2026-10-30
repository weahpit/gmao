<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Espace "Mon profil" : permet a l'utilisateur CONNECTE de mettre a jour
 * ses propres informations (nom, prenoms, mobile, email, photo) et de
 * changer son mot de passe.
 *
 * Securite :
 *  - #[IsGranted('ROLE_USER')] : accessible uniquement si authentifie.
 *  - On agit TOUJOURS sur $this->getUser() (l'utilisateur de la session),
 *    jamais sur un id passe en parametre -> impossible d'editer le profil
 *    d'un autre utilisateur (pas d'IDOR).
 *  - Jeton CSRF verifie sur chaque formulaire POST.
 *  - Upload photo : type MIME et taille controles cote serveur.
 *  - Changement de mot de passe : verification du mot de passe actuel +
 *    exigence de robustesse, hachage via le service officiel Symfony.
 */
final class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {}

    #[Route('/profil', name: 'app_profile', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        return $this->render('profile/edit.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    /**
     * Mise a jour des informations personnelles + photo.
     */
    #[Route('/profil', name: 'app_profile_update', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function update(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // 1) Protection CSRF
        if (!$this->isCsrfTokenValid('profile_update', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session expiree, veuillez reessayer.');
            return $this->redirectToRoute('app_profile');
        }

        $nom      = trim((string) $request->request->get('nom'));
        $prenoms  = trim((string) $request->request->get('prenoms'));
        $mobile   = trim((string) $request->request->get('mobile'));
        $email    = strtolower(trim((string) $request->request->get('email')));

        // 2) Validation basique cote serveur
        if ($nom === '' || $prenoms === '') {
            $this->addFlash('error', 'Le nom et les prenoms sont obligatoires.');
            return $this->redirectToRoute('app_profile');
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Adresse e-mail invalide.');
            return $this->redirectToRoute('app_profile');
        }

        // 3) Unicite de l'e-mail (si change)
        if ($email !== strtolower((string) $user->getEmail())) {
            $existant = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existant && $existant->getId() != $user->getId()) {
                $this->addFlash('error', 'Cette adresse e-mail est deja utilisee.');
                return $this->redirectToRoute('app_profile');
            }
        }

        $user->setNom(strtoupper($nom));
        $user->setPrenoms(strtoupper($prenoms));
        $user->setMobile(strtoupper($mobile));
        $user->setEmail($email);

        // 4) Photo (facultative) : whitelist MIME + taille max 2 Mo
        /** @var UploadedFile|null $photo */
        $photo = $request->files->get('photo');
        if ($photo instanceof UploadedFile) {
            $erreur = $this->validerImage($photo);
            if ($erreur !== null) {
                $this->addFlash('error', $erreur);
                return $this->redirectToRoute('app_profile');
            }
            $ext = $photo->guessExtension() ?: 'jpg';
            $filename = 'u_' . bin2hex(random_bytes(8)) . '.' . $ext;
            // On ecrit dans public/data/users/photos pour que asset('data/users/photos/...')
            // serve bien le fichier (chemin absolu robuste quel que soit le repertoire courant).
            $dossier = $this->getParameter('kernel.project_dir') . '/public/data/users/photos';
            if (!is_dir($dossier)) {
                @mkdir($dossier, 0775, true);
            }
            $photo->move($dossier, $filename);
            $user->setPhoto($filename);
        }

        $this->em->flush();
        $this->addFlash('success', 'Votre profil a ete mis a jour.');

        return $this->redirectToRoute('app_profile');
    }

    /**
     * Changement de mot de passe.
     */
    #[Route('/profil/mot-de-passe', name: 'app_profile_password', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function changePassword(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('profile_password', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Session expiree, veuillez reessayer.');
            return $this->redirectToRoute('app_profile');
        }

        $actuel     = (string) $request->request->get('mot_de_passe_actuel');
        $nouveau    = (string) $request->request->get('nouveau_mot_de_passe');
        $confirmer  = (string) $request->request->get('confirmer_mot_de_passe');

        // 1) Le mot de passe actuel doit etre correct
        if (!$this->hasher->isPasswordValid($user, $actuel)) {
            $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
            return $this->redirectToRoute('app_profile');
        }

        // 2) Robustesse minimale du nouveau mot de passe
        if (strlen($nouveau) < 8
            || !preg_match('/[A-Za-z]/', $nouveau)
            || !preg_match('/\d/', $nouveau)) {
            $this->addFlash('error', 'Le nouveau mot de passe doit faire au moins 8 caracteres et contenir au moins une lettre et un chiffre.');
            return $this->redirectToRoute('app_profile');
        }

        // 3) Confirmation
        if ($nouveau !== $confirmer) {
            $this->addFlash('error', 'La confirmation ne correspond pas au nouveau mot de passe.');
            return $this->redirectToRoute('app_profile');
        }

        // 4) Interdire de reutiliser le mot de passe actuel
        if ($this->hasher->isPasswordValid($user, $nouveau)) {
            $this->addFlash('error', 'Le nouveau mot de passe doit etre different de l\'ancien.');
            return $this->redirectToRoute('app_profile');
        }

        $user->setPassword($this->hasher->hashPassword($user, $nouveau));
        $this->em->flush();

        $this->addFlash('success', 'Votre mot de passe a ete change.');

        return $this->redirectToRoute('app_profile');
    }

    /**
     * Controle serveur d'une image uploadee.
     * Retourne un message d'erreur, ou null si tout est OK.
     */
    private function validerImage(UploadedFile $file): ?string
    {
        if (!$file->isValid()) {
            return 'Le fichier n\'a pas pu etre televerse.';
        }
        // Taille max : 2 Mo
        if ($file->getSize() > 2 * 1024 * 1024) {
            return 'La photo ne doit pas depasser 2 Mo.';
        }
        // Type MIME reel (pas l'extension du nom de fichier)
        $mimesAutorises = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($file->getMimeType(), $mimesAutorises, true)) {
            return 'Format d\'image non autorise (JPEG, PNG ou WebP uniquement).';
        }
        return null;
    }
}
