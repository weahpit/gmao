<?php

declare(strict_types=1);

namespace App\Service\Logistique;

use App\Entity\Logistique\{CvDeblocage, CvExecution, FormuleExport, Dossier, Proprietaire};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

/** Volumes en kg, controles en grammes entiers pour eviter les erreurs d'arrondi. */
final class ReglesCvs
{
    public function __construct(private EntityManagerInterface $em) {}

    public static function grammes(mixed $valeur): int
    {
        if (!is_scalar($valeur) || is_bool($valeur)) throw new \InvalidArgumentException('Volume invalide.');
        $texte = is_float($valeur) ? number_format($valeur, 3, '.', '') : trim((string) $valeur);
        $texte = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], $texte);
        if (!preg_match('/^([0-9]{1,10})(?:\.([0-9]{1,3}))?$/D', $texte, $m)) {
            throw new \InvalidArgumentException('Utilisez un nombre positif en kg, avec au maximum trois decimales.');
        }
        return (int) $m[1] * 1000 + (int) str_pad($m[2] ?? '', 3, '0');
    }

    public static function kilos(int $grammes): string
    {
        return intdiv($grammes, 1000) . '.' . str_pad((string) ($grammes % 1000), 3, '0', STR_PAD_LEFT);
    }

    public function bilanDeblocage(CvDeblocage $cv): array
    {
        $accorde = self::grammes($cv->getPoids() ?? 0);
        $executions = $this->em->getRepository(CvExecution::class)->findBy(['code_cv_deblocage' => $cv]);
        $octroye = 0;
        foreach ($executions as $execution) $octroye += self::grammes($execution->getPoids() ?? 0);
        return ['volume' => $accorde / 1000, 'octroye' => $octroye / 1000, 'restant' => ($accorde - $octroye) / 1000];
    }

    public function bilanExecution(CvExecution $execution): array
    {
        $utilise = $this->em->createQueryBuilder()->select('SUM(f.utilise)')->from(FormuleExport::class, 'f')
            ->where('f.execution = :execution')->setParameter('execution', $execution)->getQuery()->getSingleScalarResult();
        $volume = self::grammes($execution->getPoids() ?? 0);
        $historique = $this->em->createQueryBuilder()->select('SUM(d.tonnage)')->from(Dossier::class, 'd')
            ->where('(LOWER(d.cv_execution) = :id OR LOWER(d.cv_execution) = :numero)')
            ->andWhere('NOT EXISTS (SELECT f2.id FROM ' . FormuleExport::class . ' f2 WHERE f2.dossier = d)')
            ->setParameter('id', strtolower((string) $execution->getId()))->setParameter('numero', mb_strtolower((string) $execution->getNumeroCvExecution()))
            ->getQuery()->getSingleScalarResult();
        $consomme = self::grammes($utilise ?? 0) + self::grammes($historique ?? 0);
        return ['volume' => $volume / 1000, 'consomme' => $consomme / 1000, 'restant' => ($volume - $consomme) / 1000];
    }

    public function creerDeblocage(string $numero, mixed $volume, Proprietaire $proprietaire): CvDeblocage
    {
        $numero = $this->numero($numero, 25);
        $kg = self::grammes($volume);
        if ($kg <= 0) throw new \InvalidArgumentException('Le volume accorde doit etre superieur a zero.');
        if ($this->em->getRepository(CvDeblocage::class)->findOneBy(['numero_cv' => $numero])) throw new \InvalidArgumentException('Ce numero de CV deblocage existe deja.');
        $cv = (new CvDeblocage())->setNumeroCv($numero)->setPoids($kg / 1000)->setCodeProprietaire($proprietaire);
        $this->em->persist($cv);
        $this->em->flush();
        return $cv;
    }

    public function creerExecution(CvDeblocage $deblocage, string $numero, mixed $volume): CvExecution
    {
        $numero = $this->numero($numero, 25);
        if (!$deblocage->getCodeProprietaire()) throw new \InvalidArgumentException('Renseignez le proprietaire du CV de deblocage avant de creer une execution.');
        $grammes = self::grammes($volume);
        if ($grammes <= 0) throw new \InvalidArgumentException('Le volume du contrat d’execution est obligatoire et doit etre positif.');
        return $this->em->wrapInTransaction(function () use ($deblocage, $numero, $grammes): CvExecution {
            $this->em->refresh($deblocage, LockMode::PESSIMISTIC_WRITE);
            $restant = self::grammes(max(0, $this->bilanDeblocage($deblocage)['restant']));
            if ($grammes > $restant) throw new \InvalidArgumentException('Le contrat depasse le volume deblocage restant : ' . self::kilos($restant) . ' kg.');
            if ($this->em->getRepository(CvExecution::class)->findOneBy(['code_cv_deblocage' => $deblocage, 'numero_cv_execution' => $numero])) throw new \InvalidArgumentException('Ce contrat d’execution existe deja.');
            $cv = (new CvExecution())->setNumeroCvExecution($numero)->setPoids($grammes / 1000)->setCodeCvDeblocage($deblocage);
            $this->em->persist($cv);
            return $cv;
        });
    }

    /** Renseigne le volume des contrats anciens, parfois NULL dans la source. */
    public function modifierVolumeExecution(CvExecution $execution, mixed $volume): void
    {
        $kg = self::grammes($volume);
        if ($kg <= 0 || !$execution->getCodeCvDeblocage()) throw new \InvalidArgumentException('Volume positif et CV deblocage requis.');
        $this->em->wrapInTransaction(function () use ($execution, $kg): void {
            $deb = $execution->getCodeCvDeblocage();
            $this->em->refresh($deb, LockMode::PESSIMISTIC_WRITE);
            $this->em->refresh($execution, LockMode::PESSIMISTIC_WRITE);
            $bilan = $this->bilanExecution($execution);
            if ($kg < self::grammes($bilan['consomme'])) throw new \InvalidArgumentException('Volume inferieur aux formules deja consommees.');
            $total = self::grammes($this->bilanDeblocage($deb)['octroye']) - self::grammes($execution->getPoids() ?? 0) + $kg;
            if ($total > self::grammes($deb->getPoids() ?? 0)) throw new \InvalidArgumentException('Le total des executions depasse le CV deblocage.');
            $execution->setPoids($kg / 1000);
        });
    }

    public function creerFormule(CvExecution $execution, string $numero, mixed $volume, ?array $fichier = null): FormuleExport
    {
        $numero = $this->numero($numero, 60);
        if (!$execution->getCodeCvDeblocage()?->getCodeProprietaire()) throw new \InvalidArgumentException('Le CV d’execution doit etre lie a un deblocage avec proprietaire.');
        $kg = self::grammes($volume);
        if ($kg <= 0) throw new \InvalidArgumentException('Le volume de la formule doit etre positif.');
        return $this->em->wrapInTransaction(function () use ($execution, $numero, $kg, $fichier): FormuleExport {
            $this->em->refresh($execution, LockMode::PESSIMISTIC_WRITE);
            if ($kg > self::grammes(max(0, $this->bilanExecution($execution)['restant']))) throw new \InvalidArgumentException('La formule depasse le volume du contrat d’execution restant.');
            if ($this->em->getRepository(FormuleExport::class)->findOneBy(['numero' => $numero])) throw new \InvalidArgumentException('Numero de formule deja utilise.');
            $f = (new FormuleExport())->setExecution($execution)->setNumero($numero)->setVolume(self::kilos($kg));
            if ($fichier) $f->setFichier($fichier['fichier'])->setNomOriginal($fichier['nom']);
            $this->em->persist($f);
            return $f;
        });
    }

    /** Saisie progressive : couverture partielle autorisee, plafonds toujours controles. */
    public function affecter(Dossier $dossier, array $affectations, int $poidsGrammes): void
    {
        if (!$this->em->getConnection()->isTransactionActive()) throw new \LogicException('Affectation hors transaction.');
        if ($poidsGrammes < 0) throw new \InvalidArgumentException('Poids negatif interdit.');
        $fichiers = $dossier->getId() ? $this->em->getRepository(FormuleExport::class)->findBy(['dossier' => $dossier]) : [];
        $choisies = [];
        foreach ($affectations as $a) {
            if (!is_array($a)) throw new \InvalidArgumentException('Formule invalide.');
            $id = strtolower((string) ($a['id'] ?? ''));
            if ($id === '' && trim((string) ($a['volume'] ?? '')) === '') continue;
            if (!\Symfony\Component\Uid\Uuid::isValid($id) || isset($choisies[$id])) throw new \InvalidArgumentException('Formule manquante ou selectionnee deux fois.');
            $formule = $this->em->find(FormuleExport::class, \Symfony\Component\Uid\Uuid::fromString($id));
            $kg = self::grammes(($a['volume'] ?? '') === '' ? 0 : $a['volume']);
            if (!$formule) throw new \InvalidArgumentException('Formule inconnue.');
            $choisies[$id] = ['formule' => $formule, 'kg' => $kg, 'imputation' => $poidsGrammes > 0 ? $kg : 0];
        }
        $executions = [];
        foreach (array_merge($fichiers, array_column($choisies, 'formule')) as $f) {
            $e = $f->getExecution();
            $executions[(string) $e->getId()] = $e;
        }
        ksort($executions);
        foreach ($executions as $e) $this->em->refresh($e, LockMode::PESSIMISTIC_WRITE);
        $ancien = [];
        foreach ($fichiers as $f) {
            $this->em->refresh($f, LockMode::PESSIMISTIC_WRITE);
            $id = (string) $f->getExecution()->getId();
            $ancien[$id] = ($ancien[$id] ?? 0) + self::grammes($f->getUtilise());
        }
        $nouveau = []; $total = 0; $deblocageId = null;
        foreach ($choisies as $a) {
            $f = $a['formule'];
            $this->em->refresh($f, LockMode::PESSIMISTIC_WRITE);
            if ($f->getDossier() && (string) $f->getDossier()->getId() !== (string) $dossier->getId()) throw new \InvalidArgumentException('Une formule est deja affectee a un autre dossier.');
            if ($a['kg'] > self::grammes($f->getVolume())) throw new \InvalidArgumentException('Volume superieur a celui de la formule ' . $f->getNumero());
            $e = $f->getExecution(); $deb = $e->getCodeCvDeblocage();
            if (!$deb || (string) $deb->getCodeProprietaire()?->getId() !== (string) $dossier->getProprietaire()?->getId()) throw new \InvalidArgumentException('Le proprietaire du dossier et celui des CV doivent correspondre.');
            // Un dossier reste sur un seul CV deblocage ; plusieurs executions sont possibles.
            $idDeb = (string) $deb->getId();
            if ($deblocageId !== null && $deblocageId !== $idDeb) throw new \InvalidArgumentException('Les formules du dossier doivent appartenir au meme CV deblocage.');
            $deblocageId = $idDeb;
            $id = (string) $e->getId(); $nouveau[$id] = ($nouveau[$id] ?? 0) + $a['imputation']; $total += $a['imputation'];
        }
        if ($total > $poidsGrammes) throw new \InvalidArgumentException('Les volumes imputes aux formules depassent le poids net charge. Reduisez les imputations.');
        foreach ($executions as $id => $e) {
            $reste = self::grammes(max(0, $this->bilanExecution($e)['restant'])) + ($ancien[$id] ?? 0);
            if (($nouveau[$id] ?? 0) > $reste) throw new \InvalidArgumentException('Contrat ' . $e->getNumeroCvExecution() . ' insuffisant : ' . self::kilos($reste) . ' kg disponibles.');
        }
        foreach ($fichiers as $f) $f->setDossier(null)->setUtilise('0.000');
        foreach ($choisies as $a) $a['formule']->setDossier($dossier)->setUtilise(self::kilos($a['imputation']));
        $dossier->setCvDeblocage($deblocageId)->setCvExecution($nouveau ? (string) array_key_first($nouveau) : null)->setFormule($choisies !== []);
    }

    private function numero(string $numero, int $max): string
    {
        $numero = mb_strtoupper(trim($numero));
        if ($numero === '' || mb_strlen($numero) > $max) throw new \InvalidArgumentException('Numero obligatoire (maximum ' . $max . ' caracteres).');
        return $numero;
    }
}
