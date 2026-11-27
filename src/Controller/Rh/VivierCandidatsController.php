<?php
namespace App\Controller\Rh;
use App\Entity\Rh\{Candidat,Candidature,DocumentRh,OffreEmploi};
use App\Security\RhAcces;
use App\Service\Rh\{StockageDocumentRh,ReferencesRh};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
#[Route('/rh/vivier',name:'rh_vivier')]
final class VivierCandidatsController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em,private RhAcces $acces,private StockageDocumentRh $stockage,private ReferencesRh $references){}
    #[Route('',name:'',methods:['GET'])]
    public function index(Request $r): Response {
        $this->acces->exigerRh();$q=trim((string)$r->query->get('q'));$page=max(1,(int)$r->query->get('page',1));$qb=$this->em->getRepository(Candidat::class)->createQueryBuilder('c');
        if($q!=='')$qb->where('LOWER(c.nom) LIKE :q OR LOWER(c.prenoms) LIKE :q OR LOWER(c.posteRecherche) LIKE :q OR LOWER(c.telephone) LIKE :q OR LOWER(c.email) LIKE :q')->setParameter('q','%'.mb_strtolower($q).'%');
        $total=(int)(clone $qb)->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();$c=$qb->orderBy('c.createdAt','DESC')->setFirstResult(($page-1)*30)->setMaxResults(30)->getQuery()->getResult();
        return $this->render('rh/vivier/index.html.twig',['candidats'=>$c,'q'=>$q,'page'=>$page,'pages'=>max(1,(int)ceil($total/30)),'total'=>$total]);
    }
    #[Route('/nouveau',name:'_new',methods:['GET','POST'])]
    public function nouveau(Request $r): Response {return $this->formulaire($r,new Candidat(),true);}
    #[Route('/{id}',name:'_fiche',requirements:['id'=>'[0-9a-fA-F-]{36}'],methods:['GET','POST'])]
    public function fiche(Request $r,string $id): Response {$this->acces->exigerRh();$c=Uuid::isValid($id)?$this->em->find(Candidat::class,Uuid::fromString($id)):null;if(!$c)throw $this->createNotFoundException();return $this->formulaire($r,$c,false);}
    private function formulaire(Request $r,Candidat $c,bool $new): Response {
        $this->acces->exigerRh();$erreurs=[];$donnees=[];$uploads=[];
        if($r->isMethod('POST')) {
            if(!$this->isCsrfTokenValid('rh_vivier',(string)$r->request->get('_token')))$erreurs[]='Jeton expiré.';
            else try {
                $action=(string)$r->request->get('action','enregistrer');
                if($action==='recruter'){
                    $id=(string)$r->request->get('offre');$offre=Uuid::isValid($id)?$this->em->find(OffreEmploi::class,Uuid::fromString($id)):null;
                    if(!$offre||$offre->getStatut()!==OffreEmploi::PUBLIEE)throw new \InvalidArgumentException('Choisissez une offre publiée.');
                    $ex=$this->em->getRepository(Candidature::class)->findOneBy(['candidat'=>$c,'offre'=>$offre]);
                    if(!$ex){$ex=(new Candidature())->setReference($this->references->candidature())->setCandidat($c)->setOffre($offre)->setDateSoumission(new \DateTimeImmutable('today'))->setStatut(Candidature::RECUE)->setObservations('Dossier issu du vivier / accueil spontané. '.$c->getObservationsAccueil());$this->em->persist($ex);$this->em->flush();}
                    return $this->redirectToRoute('rh_recrutement_candidature',['id'=>$ex->getId()]);
                }
                $donnees=$r->request->all();$nom=trim((string)$r->request->get('nom'));$prenoms=trim((string)$r->request->get('prenoms'));$tel=trim((string)$r->request->get('telephone'));$mail=trim((string)$r->request->get('email'));
                if(!$nom||!$prenoms||(!$tel&&!$mail))throw new \InvalidArgumentException('Nom, prénoms et au moins un contact obligatoires.');
                if($mail&&!filter_var($mail,FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('Email invalide.');
                foreach(['nom'=>100,'prenoms'=>150,'telephone'=>30,'email'=>180,'adresse'=>255,'poste'=>150,'diplomes'=>20000,'experiences'=>20000,'observations'=>20000] as $k=>$max)if(mb_strlen((string)$r->request->get($k))>$max)throw new \InvalidArgumentException($k.' : '.$max.' caractères maximum.');
                $c->setNom($nom)->setPrenoms($prenoms)->setTelephone($tel?:null)->setEmail($mail?:null)->setAdresse(trim((string)$r->request->get('adresse'))?:null)->setDiplomes(trim((string)$r->request->get('diplomes'))?:null)->setExperiences(trim((string)$r->request->get('experiences'))?:null)->setPosteRecherche(trim((string)$r->request->get('poste'))?:null)->setObservationsAccueil(trim((string)$r->request->get('observations'))?:null);
                if($new)$c->setDossierSpontane(true);
                $files=$r->files->all('documents');if(count($files)>10)throw new \InvalidArgumentException('10 pièces maximum par envoi.');
                foreach($files as $file)if($file instanceof UploadedFile&&$file->getError()!==UPLOAD_ERR_NO_FILE){$doc=(new DocumentRh())->setCategorie(DocumentRh::CATEGORIE_RECRUTEMENT)->setType('dossier_candidat')->setTitre(mb_substr($file->getClientOriginalName(),0,255))->setCandidat($c)->setDeposePar($this->acces->utilisateur());$this->stockage->stocker($file,$doc);$uploads[]=$doc;$this->em->persist($doc);}
                $this->em->persist($c);$this->em->flush();$this->addFlash('success','Dossier du candidat enregistré dans le vivier.');return $this->redirectToRoute('rh_vivier_fiche',['id'=>$c->getId()]);
            }catch(\Throwable $e){$erreurs[]=$e->getMessage();}
        }
        return $this->render('rh/vivier/fiche.html.twig',['candidat'=>$c,'nouveau'=>$new,'erreurs'=>$erreurs,'donnees'=>$donnees,'documents'=>$new?[]:$this->em->getRepository(DocumentRh::class)->findBy(['candidat'=>$c]),'offres'=>$this->em->getRepository(OffreEmploi::class)->findBy(['statut'=>OffreEmploi::PUBLIEE]),'candidatures'=>$new?[]:$this->em->getRepository(Candidature::class)->findBy(['candidat'=>$c])]);
    }
}
