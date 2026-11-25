<?php

declare(strict_types=1);

namespace App\Service\Logistique;

use App\Entity\Logistique\{Dossier, SuiviEmbarquement, Client, Proprietaire, Navire, Tc, Destination};
use App\Entity\Production\{Produit, Lot};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Symfony\Component\Uid\Uuid;

final class EnregistrerEmbarquement
{
    public function __construct(private EntityManagerInterface $em, private ReglesCvs $cvs, private DocumentsExport $documents) {}

    /** Creation/edition atomique : stock, TC, formules, dossier et liste de documents. */
    public function enregistrer(array $donnees, array $conteneurs, array $formules, string $auteur, ?Dossier $dossier = null): Dossier
    {
        foreach ($conteneurs as &$tc) {
            if (is_array($tc) && is_array($tc['lots'] ?? null)) foreach ($tc['lots'] as &$l) if (is_array($l)) $l['id'] = strtolower((string) ($l['id'] ?? ''));
            unset($l);
        }
        unset($tc);
        $conteneurs = array_map(static function (array $tc): array {
            $tc['typeTc'] = $tc['typeTc'] ?? 'Tc20';
            $tc['lots'] = array_values(array_filter($tc['lots'] ?? [], static function ($l): bool {
                if (!is_array($l)) throw new \InvalidArgumentException('Ligne de lot invalide.');
                $poids = trim((string) ($l['qteExporter'] ?? ''));
                $kg = ReglesCvs::grammes($poids === '' ? 0 : $poids);
                $colis = filter_var(($l['nbCartons'] ?? '') === '' ? 0 : $l['nbCartons'], FILTER_VALIDATE_INT);
                if ($colis === false || $colis < 0 || $colis > 10000000) throw new \InvalidArgumentException('Nombre de sacs invalide.');
                return trim((string) ($l['id'] ?? '')) !== '' || $kg !== 0 || $colis !== 0;
            }));
            return $tc;
        }, $conteneurs);
        $conteneurs = array_values(array_filter($conteneurs, static fn (array $tc): bool => trim((string) ($tc['tc'] ?? '')) !== '' || trim((string) ($tc['plomb'] ?? '')) !== '' || $tc['lots'] !== []));
        $formules = array_values(array_filter($formules, static function ($a): bool {
            if (!is_array($a)) throw new \InvalidArgumentException('Formule invalide.');
            return trim((string) ($a['id'] ?? '')) !== '' || trim((string) ($a['volume'] ?? '')) !== '';
        }));
        if (count($conteneurs) > 100 || count($formules) > 30) throw new \InvalidArgumentException('Au maximum 100 conteneurs et 30 formules par dossier.');
        return $this->em->wrapInTransaction(function () use ($donnees, $conteneurs, $formules, $auteur, $dossier): Dossier {
            if ($dossier) {
                $this->em->refresh($dossier, LockMode::PESSIMISTIC_WRITE);
                if ($dossier->isCloture()) throw new \InvalidArgumentException('Rouvrez le dossier avant de le modifier.');
            }
            $creation = $dossier === null;
            $dossier ??= new Dossier();
            $numero = mb_strtoupper(trim((string) ($donnees['numero_ot'] ?? '')));
            if ($numero === '' || mb_strlen($numero) > 20) throw new \InvalidArgumentException('Numero OT obligatoire (20 caracteres maximum).');
            $existant = $this->em->getRepository(Dossier::class)->findOneBy(['numero_ot' => $numero]);
            if ($existant && (string) $existant->getId() !== (string) $dossier->getId()) throw new \InvalidArgumentException('Ce numero OT existe deja.');
            $client = $this->reference(Client::class, $donnees['client'] ?? '');
            $proprietaire = $this->reference(Proprietaire::class, $donnees['proprietaire'] ?? '');
            $produit = $this->reference(Produit::class, $donnees['produit'] ?? '');
            if ($produit->getCodeProprio() && (string) $produit->getCodeProprio()->getId() !== (string) $proprietaire->getId()) throw new \InvalidArgumentException('Ce produit appartient a un autre proprietaire.');
            if (!$creation && ((string) $dossier->getClient()?->getId() !== (string) $client->getId() || (string) $dossier->getProduit()?->getId() !== (string) $produit->getId())) throw new \InvalidArgumentException('Le client et le produit d’un dossier suivi ne peuvent pas etre changes : creez un nouveau dossier pour conserver sa tracabilite documentaire.');
            $dossier->setNumeroOt($numero)->setClient($client)->setProprietaire($proprietaire)->setProduit($produit);
            $dossier->setCodeNavire(($donnees['navire'] ?? '') !== '' ? $this->reference(Navire::class, $donnees['navire']) : null);
            $dossier->setDestination(($donnees['destination'] ?? '') !== '' ? $this->reference(Destination::class, $donnees['destination']) : null);
            if ($creation) $dossier->setCreatedAt(new \DateTimeImmutable())->setCreatedBy($auteur)->setCloture(false);
            $dossier->setUpdatedAt(new \DateTimeImmutable())->setUpdatedBy($auteur);
            $suivi = $creation ? null : $this->em->getRepository(SuiviEmbarquement::class)->findOneBy(['dossier' => $dossier]);
            if (!$creation && !$suivi && (!$dossier->getTcs()->isEmpty() || ($dossier->getTonnage() ?? 0) > 0)) throw new \InvalidArgumentException('Dossier historique sans detail des lots charges : rapprochez les donnees avant toute edition des quantites.');
            if ($suivi) $this->em->refresh($suivi, LockMode::PESSIMISTIC_WRITE);
            $suivi ??= (new SuiviEmbarquement())->setDossier($dossier);
            $ancienneReservation = []; $demandes = []; $lots = []; $numeros = [];
            // Nombre de conteneurs deja utilises par lot dans le dossier enregistre :
            // sert a tolerer les anciens dossiers ou un lot figurait deux fois, tout
            // en refusant desormais ce cas sur une nouvelle saisie.
            $anciensEmplacements = [];
            foreach ($suivi->getConteneurs() as $tc) foreach ($tc['lots'] as $l) {
                $id = $l['id']; $ancienneReservation[$id] = ($ancienneReservation[$id] ?? 0) + ReglesCvs::grammes(($l['qteExporter'] ?? '') === '' ? 0 : $l['qteExporter']);
                $anciensEmplacements[$id] = ($anciensEmplacements[$id] ?? 0) + 1;
            }
            $emplacements = [];
            foreach ($conteneurs as $tc) {
                if (!is_array($tc) || !is_array($tc['lots'] ?? null)) throw new \InvalidArgumentException('Liste des lots du conteneur invalide.');
                $numeroTc = strtoupper(trim((string) ($tc['tc'] ?? '')));
                if (mb_strlen($numeroTc) > 50 || ($numeroTc !== '' && isset($numeros[$numeroTc]))) throw new \InvalidArgumentException('Numero de conteneur trop long ou en doublon.');
                if ($numeroTc !== '') $numeros[$numeroTc] = true;
                if (!in_array($tc['typeTc'] ?? '', ['Tc20', 'Tc40'], true)) throw new \InvalidArgumentException('Type de conteneur invalide.');
                if (count($tc['lots']) > 200) throw new \InvalidArgumentException('Au maximum 200 lots par conteneur.');
                $doublons = [];
                foreach ($tc['lots'] as $l) {
                    if (!is_array($l)) throw new \InvalidArgumentException('Ligne de lot invalide.');
                    $id = (string) ($l['id'] ?? '');
                    if (!Uuid::isValid($id) || isset($doublons[$id])) throw new \InvalidArgumentException('Lot invalide ou duplique dans un conteneur.');
                    $doublons[$id] = true;
                    $kg = ReglesCvs::grammes(($l['qteExporter'] ?? '') === '' ? 0 : $l['qteExporter']);
                    $colis = filter_var(($l['nbCartons'] ?? '') === '' ? 0 : $l['nbCartons'], FILTER_VALIDATE_INT);
                    if ($colis === false || $colis < 0 || $colis > 10000000) throw new \InvalidArgumentException('Nombre de sacs invalide.');
                    $demandes[$id] = ($demandes[$id] ?? 0) + $kg;
                    $emplacements[$id] = ($emplacements[$id] ?? 0) + 1;
                }
            }
            $idsLots = array_unique(array_merge(array_keys($ancienneReservation), array_keys($demandes)));
            sort($idsLots);
            foreach ($idsLots as $id) {
                $lot = $this->reference(Lot::class, $id);
                $this->em->refresh($lot, LockMode::PESSIMISTIC_WRITE);
                $base = ReglesCvs::grammes($lot->getPoidsNetDispo() ?? $lot->getPnTh() ?? 0);
                $disponible = $base + ($ancienneReservation[$id] ?? 0);
                if (isset($demandes[$id]) && (string) $lot->getCodeProduit()?->getId() !== (string) $produit->getId()) throw new \InvalidArgumentException('Un lot ne correspond pas au produit du dossier.');
                if (($demandes[$id] ?? 0) > $disponible) throw new \InvalidArgumentException('Lot ' . $lot->getNumeroLot() . ' : seulement ' . ReglesCvs::kilos($disponible) . ' kg disponibles.');
                $limite = max(1, (int) ($anciensEmplacements[$id] ?? 1));
                if (($emplacements[$id] ?? 0) > $limite) throw new \InvalidArgumentException('Le lot ' . $lot->getNumeroLot() . ' est deja charge dans un autre conteneur : un lot ne peut etre reparti que sur un seul conteneur.');
                $lot->setPoidsNetDispo(($disponible - ($demandes[$id] ?? 0)) / 1000);
                $lots[$id] = $lot;
            }
            $snapshot = []; $total = 0;
            foreach ($dossier->getLots()->toArray() as $l) $dossier->removeLot($l);
            foreach ($dossier->getTcs()->toArray() as $tc) { $dossier->removeTc($tc); if ($tc->getCodeDossier()->isEmpty()) $this->em->remove($tc); }
            foreach ($conteneurs as $tc) {
                $lignes = []; $net = 0;
                foreach ($tc['lots'] as $l) {
                    $lot = $lots[$l['id']]; $kg = ReglesCvs::grammes(($l['qteExporter'] ?? '') === '' ? 0 : $l['qteExporter']);
                    $refNet = ReglesCvs::grammes($lot->getPnTh() ?? 0);
                    $brut = $refNet > 0 ? ($lot->getPbTht() ?? 0) * $kg / $refNet : 0;
                    $lignes[] = ['id' => (string) $lot->getId(), 'numero' => $lot->getNumeroLot(), 'nbCartons' => (int) ($l['nbCartons'] ?? 0), 'qteExporter' => ReglesCvs::kilos($kg), 'pbExporter' => round($brut, 3)];
                    $dossier->addLot($lot); $net += $kg;
                }
                $numeroTc = strtoupper(trim((string) ($tc['tc'] ?? '')));
                if ($numeroTc !== '') {
                $projection = (new Tc())->setNumeroTc($numeroTc)->setTypeTc($tc['typeTc'])->setPnTh($net / 1000)->setPnReel($net / 1000);
                $this->em->persist($projection); $dossier->addTc($projection);
                }
                $snapshot[] = ['tc' => $numeroTc, 'typeTc' => $tc['typeTc'], 'plomb' => mb_substr(trim((string) ($tc['plomb'] ?? '')), 0, 100), 'vrac' => (bool) ($tc['vrac'] ?? false), 'lots' => $lignes];
                $total += $net;
            }
            $this->cvs->affecter($dossier, $formules, $total);
            $dossier->setTonnage($total / 1000)->setNbTc(count($snapshot));
            foreach (['date_empotage' => 'setDateEmpotage', 'date_relevage' => 'setDateRelevage', 'date_embarquement' => 'setDateEmbarquement', 'eta' => 'setEta', 'date_remise' => 'setRemiseDoc'] as $cle => $methode) {
                $v = (string) ($donnees[$cle] ?? '');
                $d = $v !== '' ? \DateTime::createFromFormat('!Y-m-d', $v) : null;
                if ($v !== '' && (!$d || $d->format('Y-m-d') !== $v)) throw new \InvalidArgumentException('Date invalide : ' . $cle);
                $dossier->$methode($d ?: null);
            }
            $traitements = [];
            foreach (['formule','cheque','fumigue','etiquette'] as $cle) {
                $v = (string) ($donnees['options-' . $cle] ?? ($cle === 'formule' && $formules !== [] ? 'oui' : 'non'));
                if (!in_array($v, ['oui', 'non'], true)) throw new \InvalidArgumentException('Choisissez Oui ou Non pour ' . $cle . '.');
                $traitements[$cle] = $v;
            }
            if ($formules !== []) $traitements['formule'] = 'oui';
            $dossier->setFormule($traitements['formule'] === 'oui');
            $dossier->setCheque($traitements['cheque'] === 'oui')->setTcFumigue($traitements['fumigue'] === 'oui')->setEtiquette($traitements['etiquette'] === 'oui');
            $certificats = [];
            foreach (['draft','pl','cert_poids','coa','phyto','co','conformite','hygiene','fumigation','halal'] as $cle) {
                $s = (string) ($donnees['cert_' . $cle] ?? 'POUR_APPROBATION');
                if (!isset(DocumentsExport::STATUTS[$s])) throw new \InvalidArgumentException('Statut documentaire invalide.');
                $certificats[$cle] = $s;
            }
            foreach (['draft' => 'setDraft', 'pl' => 'setPackingList', 'cert_poids' => 'setCertificatPoids', 'coa' => 'setCoa', 'phyto' => 'setCertificatPhyto', 'co' => 'setCo', 'conformite' => 'setCertificatConformite', 'hygiene' => 'setCertificatHygienne', 'fumigation' => 'setCertificatFumigation', 'halal' => 'setCertificatHalah'] as $cle => $methode) $dossier->$methode($certificats[$cle]);
            $montant = null;
            if (($donnees['montant'] ?? '') !== '') {
                $centimes = ReglesCvs::grammes($donnees['montant']);
                if ($centimes % 10 !== 0) throw new \InvalidArgumentException('Le montant accepte deux decimales maximum.');
                $montant = number_format($centimes / 1000, 2, '.', '');
            }
            $type = (string) ($donnees['type_montant'] ?? 'Reversement');
            if (!in_array($type, ['Reversement','Soutien'], true)) throw new \InvalidArgumentException('Type de montant invalide.');
            $suivi->setConteneurs($snapshot)->setFormulesPreparees($formules)->setCertificats($certificats)->setTraitements($traitements)
                ->setRapportEmpotage(mb_substr((string) ($donnees['rapport_empotage'] ?? ''), 0, 20000))
                ->setContratAfricao(mb_substr((string) ($donnees['contrat_africao'] ?? ''), 0, 100))
                ->setPortDestination(mb_substr((string) ($donnees['port_destination'] ?? ''), 0, 150))->setTypeMontant($type)->setMontant($montant);
            if (isset($donnees['_rapport_fichier'])) $suivi->setFichierRapport($donnees['_rapport_fichier'])->setNomRapport($donnees['_rapport_nom']);
            $this->em->persist($dossier); $this->em->persist($suivi);
            $this->em->flush();
            $this->documents->initialiser($dossier);
            $this->em->flush();
            return $dossier;
        });
    }

    private function reference(string $classe, mixed $id): object
    {
        if (!is_string($id) || !Uuid::isValid($id)) throw new \InvalidArgumentException('Reference obligatoire ou invalide.');
        $objet = $this->em->find($classe, Uuid::fromString($id));
        if (!$objet) throw new \InvalidArgumentException('Reference introuvable.');
        return $objet;
    }
}
