<?php
namespace App\Controller\Logistique;
use App\Entity\Logistique\{Proprietaire,CvDeblocage,CvExecution};
use App\Service\Logistique\{ReglesCvs,StockageDocumentsExport};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,JsonResponse};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
final class CvRapideController extends AbstractController
{
    #[Route('/logistique/cv-rapide',name:'logistique_cv_rapide',methods:['GET','POST'])]
    public function index(Request $r,EntityManagerInterface $em,ReglesCvs $cvs,StockageDocumentsExport $stock): JsonResponse {
        $ok=false;foreach(['ROLE_ADMIN','ROLE_DG','ROLE_DL','ROLE_SHIPPING','ROLE_AGENT_SHIPPING','AGENT_SHIPPING','ROLE_LOGISTIQUE','ROLE_LOGISTIQU'] as $role) if($this->isGranted($role))$ok=true;
        if(!$ok)return $this->json(['message'=>'Accès shipping requis.'],403);
        $file=null;
        try {
            $type=(string)($r->isMethod('POST')?$r->request->get('type'):$r->query->get('type'));
            $id=(string)($r->isMethod('POST')?$r->request->get('proprietaire'):$r->query->get('proprietaire'));
            if(!Uuid::isValid($id)||!($p=$em->find(Proprietaire::class,Uuid::fromString($id))))throw new \InvalidArgumentException('Sélectionnez le propriétaire du dossier.');
            if(!in_array($type,['deblocage','execution','formule'],true))throw new \InvalidArgumentException('Type de CV inconnu.');
            if($r->isMethod('GET')) {
                $parents=[];
                if($type==='execution')foreach($em->getRepository(CvDeblocage::class)->findBy(['code_proprietaire'=>$p],['numero_cv'=>'ASC']) as $cv)$parents[]=['id'=>(string)$cv->getId(),'libelle'=>$cv->getNumeroCv().' — '.$cv->getPeriode().' — '.$cvs->bilanDeblocage($cv)['restant'].' kg restants'];
                if($type==='formule')foreach($em->createQueryBuilder()->select('e')->from(CvExecution::class,'e')->join('e.code_cv_deblocage','d')->where('d.code_proprietaire = :p')->setParameter('p',$p)->getQuery()->getResult() as $cv)$parents[]=['id'=>(string)$cv->getId(),'libelle'=>$cv->getNumeroCvExecution().' — '.$cv->getPeriode().' — '.$cvs->bilanExecution($cv)['restant'].' kg restants'];
                return $this->json(['parents'=>$parents]);
            }
            if(!$this->isCsrfTokenValid('operations_logistiques',(string)$r->request->get('_token')))return $this->json(['message'=>'Jeton expiré.'],403);
            $numero=(string)$r->request->get('numero');$volume=$r->request->get('volume');$periode=(string)$r->request->get('periode');
            if($type==='deblocage'){$cv=$cvs->creerDeblocage($numero,$volume,$p,$periode);return $this->json(['id'=>(string)$cv->getId(),'libelle'=>$cv->getNumeroCv()]);}
            $parent=(string)$r->request->get('parent');if(!Uuid::isValid($parent))throw new \InvalidArgumentException('Sélectionnez le CV parent.');
            if($type==='execution'){$deb=$em->find(CvDeblocage::class,Uuid::fromString($parent));if(!$deb||(string)$deb->getCodeProprietaire()?->getId()!==$id)throw new \InvalidArgumentException('CV parent d’un autre propriétaire.');$cv=$cvs->creerExecution($deb,$numero,$volume,$periode);return $this->json(['id'=>(string)$cv->getId(),'libelle'=>$cv->getNumeroCvExecution()]);}
            $exe=$em->find(CvExecution::class,Uuid::fromString($parent));if(!$exe||(string)$exe->getCodeCvDeblocage()?->getCodeProprietaire()?->getId()!==$id)throw new \InvalidArgumentException('Exécution d’un autre propriétaire.');
            $upload=$r->files->get('fichier');if($upload instanceof UploadedFile&&$upload->getError()!==UPLOAD_ERR_NO_FILE)$file=$stock->enregistrer($upload);
            $f=$cvs->creerFormule($exe,$numero,$volume,$file);$file=null;
            return $this->json(['id'=>(string)$f->getId(),'libelle'=>$f->getNumero().' — '.$exe->getNumeroCvExecution(),'volume'=>$f->getVolume(),'proprietaire'=>$id]);
        } catch(\Throwable $e){if($file)$stock->supprimer($file['fichier']);return $this->json(['message'=>$e->getMessage()],422);}
    }
}
