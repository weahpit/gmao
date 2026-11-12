<?php

namespace App\Service\Reunion;

use App\Entity\Reunion\ParticipantReunion;
use App\Entity\Reunion\Reunion;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Envoi du compte rendu PDF d'une reunion aux participants qui ont laisse
 * une adresse email.
 *
 * - le fichier PDF est joint a chaque message ;
 * - un seul email est construit et envoye a tous les destinataires (BCC pour
 *   ne pas exposer les adresses entre elles) ;
 * - l'echange peut etre isole pour les tests ;
 * - la date du dernier envoi est memorisee sur la reunion.
 *
 * IMPORTANT : rien n'est envoye si le compte rendu est absent. L'appelant
 * (controleur admin) verifie les droits d'acces et l'existence du PDF.
 */
final class EnvoiCompteRenduReunion
{
    /** Adresse d'expedition par defaut, utilisee si le parametre mail_from est absent. */
    public const MAIL_FROM = 'admin@ndiasoft.com';

    public function __construct(
        private MailerInterface $mailer,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        #[Autowire('%reunion_comptes_rendus%')] private string $dossierComptesRendus,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        #[Autowire('%mail_from%')] private string $mailFrom = self::MAIL_FROM,
    ) {}

    /**
     * Adresses email valides des participants d'une reunion.
     *
     * @return list<string>
     */
    public function destinataires(Reunion $reunion): array
    {
        $emails = [];

        /** @var ParticipantReunion $participant */
        foreach ($reunion->getParticipants() as $participant) {
            $email = mb_strtolower(trim((string) $participant->getEmail()));

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            if (!in_array($email, $emails, true)) {
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /**
     * Envoie le compte rendu.
     *
     * @param list<string>|null $destinataires  null = tous les participants ayant un email
     *
     * @return array{envoyes:int, echecs:int, message:string}
     */
    public function envoyer(Reunion $reunion, ?array $destinataires = null, string $messageLibre = ''): array
    {
        if (!$reunion->getCompteRendu()) {
            return ['envoyes' => 0, 'echecs' => 1, 'message' => 'Aucun compte rendu PDF n\'est charge pour cette reunion.'];
        }

        $chemin = rtrim($this->projectDir, '/') . '/public/' . $this->dossierComptesRendus . $reunion->getCompteRendu();
        if (!is_file($chemin)) {
            return ['envoyes' => 0, 'echecs' => 1, 'message' => 'Le fichier du compte rendu est introuvable sur le disque.'];
        }

        $liste = $destinataires ?? $this->destinataires($reunion);

        // Nettoyage et validation.
        $valides = [];
        foreach ($liste as $adresse) {
            $adresse = mb_strtolower(trim((string) $adresse));
            if ($adresse !== '' && filter_var($adresse, FILTER_VALIDATE_EMAIL) && !in_array($adresse, $valides, true)) {
                $valides[] = $adresse;
            }
        }

        if ($valides === []) {
            return [
                'envoyes' => 0,
                'echecs'  => 0,
                'message' => 'Aucune adresse email valide : les participants n\'ont pas renseigne leur email.',
            ];
        }

        $pdf = (string) file_get_contents($chemin);
        if ($pdf === '') {
            return ['envoyes' => 0, 'echecs' => 1, 'message' => 'Le fichier du compte rendu est vide.'];
        }

        $nomPdf = 'CR-' . date('Ymd', ($reunion->getDateDebut() ?? new \DateTimeImmutable())->getTimestamp())
            . '-' . preg_replace('/[^A-Za-z0-9]+/', '-', (string) $reunion->getTitre()) . '.pdf';

        $dateReunion = $reunion->getDateDebut() ? $reunion->getDateDebut()->format('d/m/Y') : '-';

        $corps = ($messageLibre !== '' ? $messageLibre . "\n\n" : '')
            . "Bonjour,\n\n"
            . "La reunion « " . $reunion->getTitre() . " » du " . $dateReunion
            . ($reunion->getLieu() ? ' (' . $reunion->getLieu() . ')' : '')
            . " est terminee.\n"
            . "Vous trouverez ci-joint le compte rendu au format PDF.\n\n"
            . "Nombre de participants enregistres : " . $reunion->getNombreParticipants() . ".\n\n"
            . "Cordialement,\n"
            . "CITRAC — GMAO";

        // Une adresse visible obligatoire : la premiere est mise en destinataire,
        // les autres en copie cachee (BCC) pour ne pas exposer leurs adresses.
        $premier = array_shift($valides);

        $email = (new Email())
            ->from($this->mailFrom)
            ->to($premier)
            ->subject('CITRAC — Compte rendu de la reunion : ' . $reunion->getTitre())
            ->text($corps)
            ->attach($pdf, $nomPdf, 'application/pdf');

        foreach ($valides as $adresse) {
            $email->addBcc($adresse);
        }

        $total = count($valides) + 1;

        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Echec de l\'envoi du compte rendu de reunion', [
                'reunion' => (string) $reunion->getId(),
                'erreur'  => $e->getMessage(),
            ]);

            return [
                'envoyes' => 0,
                'echecs'  => $total,
                'message' => 'Envoi impossible : ' . $e->getMessage(),
            ];
        }

        $reunion->setCompteRenduEnvoyeLe(new \DateTimeImmutable())
            ->setCompteRenduEnvoyeA($total);
        $this->em->flush();

        $this->logger->info('Compte rendu de reunion envoye', [
            'reunion'      => (string) $reunion->getId(),
            'destinataires' => $total,
        ]);

        return [
            'envoyes' => $total,
            'echecs'  => 0,
            'message' => 'Compte rendu envoye a ' . $total . ' participant(s).',
        ];
    }
}
