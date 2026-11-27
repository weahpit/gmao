<?php
namespace App\Controller\References;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request,Response};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[IsGranted('ROLE_ADMIN')]
final class EditionReferencesController extends AbstractController
{
    private const CATALOGUE = [
        'categorie'=>'App\\Entity\\Categorie',
        'criticite'=>'App\\Entity\\Criticite',
        'directions'=>'App\\Entity\\Directions',
        'emplacement'=>'App\\Entity\\Emplacement',
        'equipement'=>'App\\Entity\\Equipement',
        'equipementtype'=>'App\\Entity\\EquipementType',
        'etatequipement'=>'App\\Entity\\EtatEquipement',
        'famille'=>'App\\Entity\\Famille',
        'fournisseur'=>'App\\Entity\\Fournisseur',
        'marque'=>'App\\Entity\\Marque',
        'natureequipement'=>'App\\Entity\\NatureEquipement',
        'poste'=>'App\\Entity\\Poste',
        'services'=>'App\\Entity\\Services',
        'statutalerte'=>'App\\Entity\\StatutAlerte',
        'typeemplacement'=>'App\\Entity\\TypeEmplacement',
        'typeeq'=>'App\\Entity\\TypeEq',
        'typeequipement'=>'App\\Entity\\TypeEquipement',
        'typezoneexploitation'=>'App\\Entity\\TypeZoneExploitation',
        'zoneexploitation'=>'App\\Entity\\ZoneExploitation',
        'groupe'=>'App\\Entity\\Groupe',
        'menu'=>'App\\Entity\\Menu',
        'widget'=>'App\\Entity\\Widget',
        'permission'=>'App\\Entity\\Permission',
        'menupermission'=>'App\\Entity\\MenuPermission',
        'capptdb'=>'App\\Entity\\CAppTdb',
        'logistique-approbation'=>'App\\Entity\\Logistique\\Approbation',
        'logistique-banque'=>'App\\Entity\\Logistique\\Banque',
        'logistique-client'=>'App\\Entity\\Logistique\\Client',
        'logistique-destination'=>'App\\Entity\\Logistique\\Destination',
        'logistique-doctype'=>'App\\Entity\\Logistique\\DocType',
        'logistique-magasin'=>'App\\Entity\\Logistique\\Magasin',
        'logistique-modeledoc'=>'App\\Entity\\Logistique\\ModeleDoc',
        'logistique-navire'=>'App\\Entity\\Logistique\\Navire',
        'logistique-pays'=>'App\\Entity\\Logistique\\Pays',
        'logistique-proprietaire'=>'App\\Entity\\Logistique\\Proprietaire',
        'logistique-transitaire'=>'App\\Entity\\Logistique\\Transitaire',
        'logistique-compagnienavigation'=>'App\\Entity\\Logistique\\CompagnieNavigation',
        'maintenance-natureintervention'=>'App\\Entity\\Maintenance\\NatureIntervention',
        'maintenance-origineintervention'=>'App\\Entity\\Maintenance\\OrigineIntervention',
        'maintenance-priorite'=>'App\\Entity\\Maintenance\\Priorite',
        'maintenance-resultatintervention'=>'App\\Entity\\Maintenance\\ResultatIntervention',
        'maintenance-soustraitant'=>'App\\Entity\\Maintenance\\SousTraitant',
        'maintenance-statutintervention'=>'App\\Entity\\Maintenance\\StatutIntervention',
        'maintenance-typeintervention'=>'App\\Entity\\Maintenance\\TypeIntervention',
        'production-produit'=>'App\\Entity\\Production\\Produit',
        'stock-rubrique'=>'App\\Entity\\Stock\\Rubrique',
        'stock-unite'=>'App\\Entity\\Stock\\Unite',
        'rh-critereevaluation'=>'App\\Entity\\Rh\\CritereEvaluation'
    ];
    #[Route('/references/edition',name:'references_edition',methods:['GET'])]
    public function index(): Response {return $this->render('references/edition_index.html.twig',['catalogue'=>self::CATALOGUE]);}
    #[Route('/references/edition/{type}',name:'references_edition_liste',methods:['GET'])]
    public function liste(string $type,Request $r,EntityManagerInterface $em): Response {
        $classe=self::CATALOGUE[$type]??null;if(!$classe)throw $this->createNotFoundException();$m=$em->getClassMetadata($classe);$id=$m->getSingleIdentifierFieldName();$q=trim((string)$r->query->get('q'));$page=max(1,(int)$r->query->get('page',1));$qb=$em->getRepository($classe)->createQueryBuilder('r');$or=[];
        foreach($m->getFieldNames() as $field)if(in_array($m->getTypeOfField($field),['string','text'],true))$or[]='LOWER(r.'.$field.') LIKE :q';
        if($q!==''&&$or)$qb->where(implode(' OR ',$or))->setParameter('q','%'.mb_strtolower($q).'%');
        $total=(int)(clone $qb)->select('COUNT(r.'.$id.')')->getQuery()->getSingleScalarResult();$rows=$qb->orderBy('r.'.$id,'ASC')->setFirstResult(($page-1)*30)->setMaxResults(30)->getQuery()->getResult();$donnees=[];
        foreach($rows as $o){$vals=[];foreach($m->getFieldNames() as $f){$v=$m->getFieldValue($o,$f);$vals[$f]=$this->afficher($v);}$donnees[]=['id'=>(string)$m->getFieldValue($o,$id),'valeurs'=>$vals];}
        return $this->render('references/edition_liste.html.twig',['type'=>$type,'champs'=>$m->getFieldNames(),'lignes'=>$donnees,'q'=>$q,'page'=>$page,'pages'=>max(1,(int)ceil($total/30))]);
    }
    #[Route('/references/edition/{type}/{id}',name:'references_edition_form',methods:['GET','POST'])]
    public function modifier(string $type,string $id,Request $r,EntityManagerInterface $em): Response {
        $classe=self::CATALOGUE[$type]??null;if(!$classe)throw $this->createNotFoundException();$m=$em->getClassMetadata($classe);$o=$em->find($classe,$id);if(!$o)throw $this->createNotFoundException();$erreurs=[];$fields=[];$relations=[];
        foreach($m->getFieldNames() as $f){if($m->isIdentifier($f))continue;$mapping=$m->getFieldMapping($f);$fields[$f]=['type'=>$m->getTypeOfField($f),'nullable'=>(bool)($mapping['nullable']??false),'length'=>$mapping['length']??null,'valeur'=>$this->afficher($m->getFieldValue($o,$f))];}
        foreach($m->getAssociationNames() as $f)if($m->isSingleValuedAssociation($f)&&$m->isAssociationInverseSide($f)===false){$target=$m->getAssociationTargetClass($f);$targetMeta=$em->getClassMetadata($target);$current=$m->getFieldValue($o,$f);$choices=[];if(in_array($target,self::CATALOGUE,true)){foreach($em->getRepository($target)->findAll() as $ref){$rid=(string)$targetMeta->getFieldValue($ref,$targetMeta->getSingleIdentifierFieldName());$choices[]=['id'=>$rid,'libelle'=>$this->nom($ref,$targetMeta)];}}else continue;$a=$m->getAssociationMapping($f);$relations[$f]=['valeur'=>$current?(string)$targetMeta->getFieldValue($current,$targetMeta->getSingleIdentifierFieldName()):'','choix'=>$choices,'target'=>$target,'nullable'=>(bool)($a['joinColumns'][0]['nullable']??true)];}
        if($r->isMethod('POST')) {
            if(!$this->isCsrfTokenValid('ref_edit_'.$type.'_'.$id,(string)$r->request->get('_token')))$erreurs[]='Jeton expiré.';
            else try{
                foreach($fields as $f=>$info){$v=(string)$r->request->get($f,'');$fields[$f]['valeur']=$v;$value=$this->convertir($v,$info);$m->setFieldValue($o,$f,$value);}
                foreach($relations as $f=>$info){$rid=(string)$r->request->get($f,'');$ref=$rid!==''?$em->find($info['target'],$rid):null;if(!$ref&&!$info['nullable'])throw new \InvalidArgumentException($f.' : référence obligatoire.');if($rid!==''&&!$ref)throw new \InvalidArgumentException($f.' : référence inexistante.');$m->setFieldValue($o,$f,$ref);}
                $em->flush();$this->addFlash('success','Référence modifiée.');return $this->redirectToRoute('references_edition_liste',['type'=>$type]);
            }catch(\Throwable $e){$erreurs[]=$e->getMessage();}
        }
        return $this->render('references/edition_form.html.twig',['type'=>$type,'id'=>$id,'fields'=>$fields,'relations'=>$relations,'erreurs'=>$erreurs]);
    }
    private function afficher(mixed $v): string {if($v===null)return '';if($v instanceof \DateTimeInterface)return $v->format('Y-m-d\TH:i:s');if(is_bool($v))return $v?'1':'0';if(is_array($v))return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);return (string)$v;}
    private function nom(object $o,object $m): string {foreach(['getLibelle','getDenomination','getNomClient','getNom','getNomModele','getNomMenu'] as $method)if(method_exists($o,$method))return (string)$o->$method();return (string)$m->getFieldValue($o,$m->getSingleIdentifierFieldName());}
    private function convertir(string $v,array $i): mixed {
        if($v===''&&$i['nullable'])return null;$type=$i['type'];
        if($type==='boolean')return $v==='1';
        if(in_array($type,['integer','smallint','bigint'],true)){if(!preg_match('/^-?\d+$/D',$v))throw new \InvalidArgumentException('Entier invalide.');return (int)$v;}
        if(in_array($type,['float','decimal'],true)){if(!is_numeric(str_replace(',','.',$v)))throw new \InvalidArgumentException('Nombre invalide.');return $type==='decimal'?str_replace(',','.',$v):(float)str_replace(',','.',$v);}
        if(str_contains($type,'date')||str_contains($type,'time')){if($v==='')throw new \InvalidArgumentException('Date obligatoire.');$date=new \DateTimeImmutable($v);return str_contains($type,'immutable')?$date:\DateTime::createFromImmutable($date);}
        if($type==='json')return json_decode($v,true,32,JSON_THROW_ON_ERROR);
        if($i['length']&&mb_strlen($v)>$i['length'])throw new \InvalidArgumentException('Texte trop long : '.$i['length'].' caractères maximum.');return $v;
    }
}
