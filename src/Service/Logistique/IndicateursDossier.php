<?php
namespace App\Service\Logistique;
use App\Entity\Logistique\{Dossier,DocsDossier};
use Doctrine\ORM\EntityManagerInterface;
final class IndicateursDossier
{
    public function __construct(private EntityManagerInterface $em) {}
    public static function calcul(array $etapes,int $documents,int $transmis,?\DateTimeInterface $embarquement,?\DateTimeInterface $remise,?\DateTimeImmutable $aujourdhui=null): array {
        $total=count($etapes)+$documents;
        $realise=count(array_filter($etapes))+$transmis;
        $jours=null;$incoherent=false;$now=$aujourdhui??new \DateTimeImmutable('today');
        if($embarquement){$depart=new \DateTimeImmutable($embarquement->format('Y-m-d'));$fin=$remise?new \DateTimeImmutable($remise->format('Y-m-d')):$now;
            if($remise&&$fin<$depart)$incoherent=true;
            if($depart<=$now||$remise)$jours=max(0,(int)$depart->diff($fin)->format('%r%a'));
        }
        return ['pourcentage'=>$total ? (int) floor(min($realise,$total)*100/$total):0,'total'=>$total,'realise'=>$realise,'jours'=>$jours,'depasse'=>$jours!==null&&$jours>5,'incoherent'=>$incoherent];
    }
    public function pourDossiers(array $dossiers): array {
        if(!$dossiers)return []; $ids=array_map(static fn($d)=>$d->getId(),$dossiers);
        $rows=$this->em->createQueryBuilder()->select('IDENTITY(doc.code_dossier) AS dossier','COUNT(doc.id) AS total','SUM(CASE WHEN doc.transmisLe IS NOT NULL THEN 1 ELSE 0 END) AS transmis')->from(DocsDossier::class,'doc')->where('doc.code_dossier IN (:ids)')->setParameter('ids',$ids)->groupBy('doc.code_dossier')->getQuery()->getArrayResult();
        $docs=[];foreach($rows as $row)$docs[(string)$row['dossier']]=$row;
        $out=[];foreach($dossiers as $d){$row=$docs[(string)$d->getId()]??['total'=>0,'transmis'=>0];
            $etapes=[(bool)$d->getNumeroOt(),(bool)$d->getDateOt(),(bool)$d->getCodeNavire(),(bool)$d->getBooking(),($d->getTonnage()??0)>0,(bool)$d->isFormule(),(bool)$d->getDateEmpotage(),(bool)$d->getDateRelevage(),(bool)$d->getDateEmbarquement(),(bool)$d->getNumeroBl(),(bool)$d->getDateBl(),(bool)$d->getRemiseDoc()];
            // Une étape "documents attendus" non réalisée empêche 100% sans modèle.
            $total=(int)$row['total'];if(!$total)$etapes[]=false;
            $out[(string)$d->getId()]=self::calcul($etapes,$total,(int)$row['transmis'],$d->getDateEmbarquement(),$d->getRemiseDoc());
        }return $out;
    }
}
