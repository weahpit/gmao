<?php
namespace App\Controller\Api;
use App\Entity\Rh\{Employe,Pointage};
use App\Repository\Rh\PointageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,JsonResponse};
use Symfony\Component\Routing\Attribute\Route;
use Psr\Log\LoggerInterface;
/** API machine-à-machine : aucun compte humain / aucune session requis. */
final class ApiPresenceZktecoController extends AbstractController
{
    #[Route('/api/presences/zkteco',name:'api_presences_zkteco',methods:['POST'])]
    public function recevoir(Request $r,EntityManagerInterface $em,PointageRepository $repo,LoggerInterface $logger): JsonResponse {
        $attendu=(string)($_ENV['ZKTECO_API_TOKEN']??$_SERVER['ZKTECO_API_TOKEN']??getenv('ZKTECO_API_TOKEN')?:'');
        $auth=(string)$r->headers->get('Authorization');
        if(strlen($attendu)<32)return $this->json(['message'=>'API de présence non configurée.'],503);
        if(!preg_match('/^Bearer (.+)$/D',$auth,$match)||!hash_equals($attendu,$match[1]))return $this->json(['message'=>'Authentification refusée.'],401);
        if(!$r->isSecure())return $this->json(['message'=>'HTTPS obligatoire.'],400);
        if(strlen($r->getContent())>1000000)return $this->json(['message'=>'Lot supérieur à 1 Mo.'],413);
        try {
            $data=json_decode($r->getContent(),true,16,JSON_THROW_ON_ERROR);$passages=$data['passages']??null;$source=(string)($data['source']??'');
            if(!preg_match('/^[A-Za-z0-9_-]{1,60}$/D',$source)||!is_array($passages)||count($passages)>1000)throw new \InvalidArgumentException('Source obligatoire, 1000 passages maximum.');
            $tz=new \DateTimeZone('Africa/Abidjan');$normalized=[];$days=[];
            foreach($passages as $p){if(!is_array($p))throw new \InvalidArgumentException('Passage invalide.');$badge=trim((string)($p['badge']??''));$texte=(string)($p['quand']??'');
                if($badge===''||mb_strlen($badge)>60||str_contains($badge,'|')||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/D',$texte))throw new \InvalidArgumentException('Badge ou horodatage invalide.');
                $dt=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s',$texte,$tz);if(!$dt||$dt->format('Y-m-d\TH:i:s')!==$texte||$dt>new \DateTimeImmutable('tomorrow',$tz))throw new \InvalidArgumentException('Date invalide ou future.');
                $userid=trim((string)($p['userid']??''));$nom=trim((string)($p['nom']??''));$genre=trim((string)($p['genre']??''));$type=strtoupper(trim((string)($p['type']??'')));$capteur=trim((string)($p['capteur']??''));
                foreach(['userid'=>[$userid,60],'nom'=>[$nom,255],'genre'=>[$genre,30],'type'=>[$type,10],'capteur'=>[$capteur,60]] as $field=>[$value,$limit]) if(mb_strlen($value)>$limit||str_contains($value,'|'))throw new \InvalidArgumentException('Champ invalide : '.$field);
                $hash=hash('sha256',$source.'|'.$badge.'|'.$texte.'|'.$userid.'|'.$type.'|'.$capteur);
                $normalized[]=['badge'=>$badge,'quand'=>$dt,'userid'=>$userid,'nom'=>$nom,'genre'=>$genre,'type'=>$type,'capteur'=>$capteur,'hash'=>$hash];$days[$badge.'|'.$dt->format('Y-m-d')]=[$badge,$dt->format('Y-m-d')];
            }
            $debut=(string)($_ENV['ZKTECO_HEURE_ARRIVEE']??'07:30');$fin=(string)($_ENV['ZKTECO_HEURE_DEPART']??'16:30');$tolerance=max(0,(int)($_ENV['ZKTECO_TOLERANCE']??5));
            $minutes=static function(string $heure): int {if(!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/D',$heure,$m))throw new \InvalidArgumentException('Horaires ZKTeco invalides côté serveur.');return (int)$m[1]*60+(int)$m[2];};$debutMin=$minutes($debut);$finMin=$minutes($fin);
            $c=$em->getConnection();$unknown=[];$crees=0;$maj=0;$ajoutes=0;
            $em->wrapInTransaction(function()use($c,$em,$repo,$normalized,$source,$days,$tz,$debutMin,$finMin,$tolerance,&$unknown,&$crees,&$maj,&$ajoutes){
                // Verrou transactionnel global : protège les chevauchements de lots / sources.
                $c->executeQuery("SELECT pg_advisory_xact_lock(724601127)");
                foreach($normalized as $p)$ajoutes+=$c->executeStatement('INSERT INTO rh_zkteco_passage (empreinte,source,badge,quand,userid,nom,genre,type_pointage,capteur,recu_le) VALUES (:hash,:source,:badge,:quand,:userid,:nom,:genre,:type,:capteur,CURRENT_TIMESTAMP) ON CONFLICT (empreinte) DO NOTHING',['hash'=>$p['hash'],'source'=>$source,'badge'=>$p['badge'],'quand'=>$p['quand']->format('Y-m-d H:i:s'),'userid'=>$p['userid'],'nom'=>$p['nom'],'genre'=>$p['genre'],'type'=>$p['type'],'capteur'=>$p['capteur']]);
                foreach($days as [$badge,$day]){
                    // Pas d’exclusion des salariés sortis : les historiques de présence restent utiles.
                    $matches=$em->createQueryBuilder()->select('e')->from(Employe::class,'e')->where('e.codePointage = :badge OR e.matricule = :badge')->setParameter('badge',$badge)->setMaxResults(2)->getQuery()->getResult();
                    if(count($matches)!==1){$unknown[$badge]=count($matches)>1?'Badge ambigu':'Badge sans employé';continue;}$e=$matches[0];
                    $row=$c->fetchAssociative('SELECT MIN(quand) AS arrivee,MAX(quand) AS depart FROM rh_zkteco_passage WHERE badge=:badge AND quand>=:du AND quand<:au',['badge'=>$badge,'du'=>$day.' 00:00:00','au'=>(new \DateTimeImmutable($day,$tz))->modify('+1 day')->format('Y-m-d').' 00:00:00']);
                    $arrivee=new \DateTimeImmutable($row['arrivee'],$tz);$depart=new \DateTimeImmutable($row['depart'],$tz);$jour=new \DateTimeImmutable($day,$tz);$pointage=$repo->findUnPourEmployeEtJour($e,$jour);
                    if(!$pointage){$pointage=(new Pointage())->setEmploye($e)->setJour($jour);$em->persist($pointage);$crees++;}else{$maj++;}
                    // Une seule présence ne crée pas artificiellement d’heure de départ.
                    $ha=\DateTime::createFromFormat('!H:i:s',$arrivee->format('H:i:s'));$hd=$depart>$arrivee?\DateTime::createFromFormat('!H:i:s',$depart->format('H:i:s')):null;
                    if($pointage->getHeureArrivee()&&$pointage->getHeureArrivee()->format('H:i:s')<$ha->format('H:i:s'))$ha=$pointage->getHeureArrivee();
                    if($pointage->getHeureDepart()&&(!$hd||$pointage->getHeureDepart()->format('H:i:s')>$hd->format('H:i:s')))$hd=$pointage->getHeureDepart();
                    $arrMin=(int)$ha->format('H')*60+(int)$ha->format('i');$depMin=$hd?(int)$hd->format('H')*60+(int)$hd->format('i'):null;
                    $pointage->setHeureArrivee($ha)->setHeureDepart($hd)->setRetardMinutes(max(0,$arrMin-$debutMin-$tolerance))->setHeuresSupplementaires($depMin===null?0:max(0,$depMin-$finMin))->setCommentaire('POINTAGE ZKTECO API — BADGE '.$badge);
                }
            });
            return $this->json(['message'=>'Lot reçu.','recus'=>count($normalized),'nouveauxPassages'=>$ajoutes,'doublons'=>count($normalized)-$ajoutes,'pointagesCrees'=>$crees,'pointagesMaj'=>$maj,'badgesNonRapproches'=>$unknown,'acquittes'=>array_values(array_unique(array_column($normalized,'hash')))]);
        }catch(\JsonException|\InvalidArgumentException $e){return $this->json(['message'=>$e->getMessage()],422);}catch(\Throwable $e){$ref=bin2hex(random_bytes(6));$logger->error('Reception ZKTeco en echec {reference}',['reference'=>$ref,'exception'=>$e]);return $this->json(['message'=>'Enregistrement des présences impossible. Consultez le journal serveur.','reference'=>$ref],500);}
    }
}
