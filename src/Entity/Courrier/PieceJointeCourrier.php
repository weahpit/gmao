<?php

namespace App\Entity\Courrier;

use App\Repository\Courrier\PieceJointeCourrierRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Piece jointe d'un courrier (scan du courrier recu, copie de la lettre
 * envoyee, annexe, justificatif...).
 *
 * Le fichier physique est stocke dans public/data/courriers/AAAA/MM/ ;
 * la base ne conserve que son nom genere, son nom d'origine, sa taille
 * et son type MIME.
 */
#[ORM\Entity(repositoryClass: PieceJointeCourrierRepository::class)]
#[ORM\Table(name: 'courrier_piece_jointe')]
class PieceJointeCourrier
{
    /** Extensions acceptees (controlees aussi dans le controleur). */
    public const EXTENSIONS = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 'txt', 'csv', 'zip'];

    /** Taille maximale d'un fichier : 20 Mo. */
    public const TAILLE_MAX = 20 * 1024 * 1024;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: Courrier::class, inversedBy: 'piecesJointes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Courrier $courrier = null;

    /** Nom du fichier sur le disque (relatif a data/courriers/). */
    #[ORM\Column(length: 255)]
    private ?string $fichier = null;

    /** Nom d'origine, tel que le fichier a ete depose. */
    #[ORM\Column(length: 255)]
    private ?string $nomOriginal = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $typeMime = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $taille = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid { return $this->id; }

    public function getCourrier(): ?Courrier { return $this->courrier; }
    public function setCourrier(?Courrier $courrier): static { $this->courrier = $courrier; return $this; }

    public function getFichier(): ?string { return $this->fichier; }
    public function setFichier(?string $fichier): static { $this->fichier = $fichier; return $this; }

    public function getNomOriginal(): ?string { return $this->nomOriginal; }
    public function setNomOriginal(?string $nomOriginal): static { $this->nomOriginal = $nomOriginal; return $this; }

    public function getTypeMime(): ?string { return $this->typeMime; }
    public function setTypeMime(?string $typeMime): static { $this->typeMime = $typeMime; return $this; }

    public function getTaille(): ?int { return $this->taille; }
    public function setTaille(?int $taille): static { $this->taille = $taille; return $this; }

    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $commentaire): static { $this->commentaire = $commentaire; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function getExtension(): string
    {
        return strtolower(pathinfo((string) $this->nomOriginal, PATHINFO_EXTENSION));
    }

    public function isPdf(): bool { return $this->getExtension() === 'pdf'; }

    public function isImage(): bool { return in_array($this->getExtension(), ['png', 'jpg', 'jpeg', 'gif', 'webp'], true); }

    /** Taille lisible (ex. « 1,2 Mo »). */
    public function getTailleLisible(): string
    {
        $o = (int) $this->taille;
        if ($o <= 0) {
            return '—';
        }
        if ($o < 1024) {
            return $o . ' o';
        }
        if ($o < 1024 * 1024) {
            return number_format($o / 1024, 0, ',', ' ') . ' Ko';
        }

        return number_format($o / (1024 * 1024), 1, ',', ' ') . ' Mo';
    }
}
