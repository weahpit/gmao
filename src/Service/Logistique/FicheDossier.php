<?php
namespace App\Service\Logistique;
use App\Entity\Logistique\{Dossier,SuiviEmbarquement,FormuleExport};
use App\Entity\Production\{Lot,LigneOrdreProduction};
use App\Entity\Stock\LotDechargement;
use Doctrine\ORM\EntityManagerInterface;
final class FicheDossier
{
    public function __construct(private EntityManagerInterface $em,private IndicateursDossier $indicateurs){}
    public function donnees(Dossier $d): array {
        $meta=$this->em->getClassMetadata(Dossier::class);$champs=[];
        foreach($meta->getFieldNames() as $f){$v=$meta->getFieldValue($d,$f);if($v instanceof \DateTimeInterface)$v=$v->format('d/m/Y H:i');elseif(is_bool($v))$v=$v?'OUI':'NON';elseif($v instanceof \Stringable)$v=(string)$v;
            $champs[str_replace('_',' ',preg_replace('/(?<!^)[A-Z]/',' $0',$f))]=$v;
        }
        $champs['Client']=$d->getClient()?->getNomClient();$champs['Produit']=$d->getProduit()?->getLibelle();$champs['Propriétaire']=$d->getProprietaire()?->getDenomination();$champs['Navire']=$d->getCodeNavire()?->getNom();$champs['Destination']=$d->getDestination()?->getDenomination();$champs['Banque de nantissement']=$d->getBanqueNantissement()?->getLibelle();$champs['Transitaire']=$d->getTransitaire()?->getLibelle();$champs['Compagnie de navigation']=$d->getCompagnieNavigation()?->getLibelle();
        $s=$this->em->getRepository(SuiviEmbarquement::class)->findOneBy(['dossier'=>$d]);
        $essentiel=['numero_ot'=>$d->getNumeroOt(),'date_ot'=>$d->getDateOt(),'tonnage'=>$d->getTonnage(),'nb_tc'=>$d->getNbTc(),'navire'=>$d->getCodeNavire()?->getNom(),'booking'=>$d->getBooking(),'port_embarquement'=>$d->getPortEmbarquement(),'numero_bl'=>$d->getNumeroBl(),'date_bl'=>$d->getDateBl(),'eta'=>$d->getEta(),'date_empotage'=>$d->getDateEmpotage(),'date_relevage'=>$d->getDateRelevage(),'date_embarquement'=>$d->getDateEmbarquement(),'remise_doc'=>$d->getRemiseDoc(),'cloture'=>$d->isCloture(),'client'=>$d->getClient()?->getNomClient(),'produit'=>$d->getProduit()?->getLibelle(),'proprietaire'=>$d->getProprietaire()?->getDenomination(),'destination'=>$d->getDestination()?->getDenomination(),'banque'=>$d->getBanqueNantissement()?->getLibelle(),'transitaire'=>$d->getTransitaire()?->getLibelle(),'compagnie'=>$d->getCompagnieNavigation()?->getLibelle()];
        return ['statuts'=>DocumentsExport::STATUTS,'essentiel'=>$essentiel,'dossier'=>$d,'suivi'=>$s,'champs'=>$champs,'documents'=>$d->getDocsDossiers(),'historiques'=>$d->getDocumentations(),'photosHistoriques'=>$d->getPhotosDocs(),'formules'=>$this->em->getRepository(FormuleExport::class)->findBy(['dossier'=>$d]),'origines'=>$this->origines($d),'indicateur'=>$this->indicateurs->pourDossiers([$d])[(string)$d->getId()]];
    }
    private function origines(Dossier $d): array {
        $out=[];foreach($d->getLots() as $lot){$visites=[];$camions=[];$this->chercher($lot,$visites,$camions,0);$out[]=['lot'=>$lot->getNumeroLot(),'camions'=>array_values($camions),'origineLibre'=>$lot->getOrigine()];}return $out;
    }
    private function chercher(Lot $lot,array &$visites,array &$camions,int $niveau): void {
        $id=(string)$lot->getId();if(isset($visites[$id])||$niveau>30)return;$visites[$id]=true;
        foreach($this->em->getRepository(LotDechargement::class)->findBy(['lot'=>$lot]) as $l){$c=$l->getDechargement();if($c)$camions[(string)$c->getId()]=['numero'=>$c->getCode(),'immatriculation'=>$c->getImmatriculation(),'lotSource'=>$lot->getNumeroLot()];}
        foreach($lot->getLotsOrigine() as $origine)$this->chercher($origine,$visites,$camions,$niveau+1);
        foreach($this->em->getRepository(LigneOrdreProduction::class)->findBy(['lotFini'=>$lot]) as $l)if($l->getLotFeve())$this->chercher($l->getLotFeve(),$visites,$camions,$niveau+1);
    }
}
