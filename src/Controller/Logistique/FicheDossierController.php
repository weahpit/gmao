<?php
namespace App\Controller\Logistique;

use App\Entity\Logistique\Dossier;
use App\Service\Logistique\{FicheDossier,PhotosEmpotage};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Response,BinaryFileResponse,StreamedResponse};
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class FicheDossierController extends AbstractController
{
    private function dossier(string $id, EntityManagerInterface $em): Dossier {
        $ok=false;
        foreach(['ROLE_ADMIN','ROLE_DG','ROLE_DL','ROLE_SHIPPING','ROLE_AGENT_SHIPPING','AGENT_SHIPPING','ROLE_LOGISTIQUE','ROLE_LOGISTIQU'] as $role) if($this->isGranted($role)) $ok=true;
        if(!$ok) throw $this->createAccessDeniedException();
        $d=Uuid::isValid($id)?$em->find(Dossier::class,Uuid::fromString($id)):null;
        if(!$d) throw $this->createNotFoundException();
        return $d;
    }
    #[Route('/logistique/embarquements/{id}/fiche',name:'logistique_dossier_fiche',methods:['GET'])]
    public function fiche(string $id,EntityManagerInterface $em,FicheDossier $service): Response {
        return $this->render('logistique/embarquement/fiche.html.twig',$service->donnees($this->dossier($id,$em)));
    }
    #[Route('/logistique/embarquements/{id}/photos/{nom}',name:'logistique_photo_empotage',methods:['GET'])]
    public function photo(string $id,string $nom,EntityManagerInterface $em,PhotosEmpotage $photos): BinaryFileResponse {
        $d=$this->dossier($id,$em);
        $s=$em->getRepository(\App\Entity\Logistique\SuiviEmbarquement::class)->findOneBy(['dossier'=>$d]);
        if(!in_array($nom,array_column($s?->getPhotosEmpotage()??[],'fichier'),true)||!($p=$photos->chemin($nom))) throw $this->createNotFoundException();
        $r=$this->file($p,$nom,'inline');
        $r->headers->set('Content-Type','image/jpeg');$r->headers->set('Cache-Control','private, no-store');$r->headers->set('X-Content-Type-Options','nosniff');return $r;
    }
    #[Route('/logistique/embarquements/{id}/export/{format}',name:'logistique_dossier_export',requirements:['format'=>'pdf|csv'],methods:['GET'])]
    public function exporter(string $id,string $format,EntityManagerInterface $em,FicheDossier $service,PhotosEmpotage $photos,LoggerInterface $logger): Response {
        $dossier=$this->dossier($id,$em);
        try {
            $data=$service->donnees($dossier);
            return $format==='pdf' ? $this->pdf($dossier,$data,$photos) : $this->csv($dossier,$data);
        } catch(\Throwable $e) {
            $ref=bin2hex(random_bytes(6));
            $logger->error('Export fiche dossier en echec {reference}',['reference'=>$ref,'format'=>$format,'exception'=>$e]);
            $this->addFlash('danger','Export '.strtoupper($format).' impossible. Reference : '.$ref.'. Consultez le journal serveur.');
            return $this->redirectToRoute('logistique_dossier_fiche',['id'=>$id]);
        }
    }
    private function nom(Dossier $d): string { return 'CITRAC-dossier-'.preg_replace('/[^a-zA-Z0-9_-]/','_',(string)$d->getNumeroOt()); }
    private function texte(mixed $v): string {
        return match(true) {
            $v===null=>'', is_bool($v)=>$v?'OUI':'NON', is_scalar($v)=>(string)$v,
            $v instanceof \DateTimeInterface=>$v->format('d/m/Y H:i'),
            $v instanceof \Stringable=>(string)$v,
            is_array($v)=>(string)json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), default=>'',
        };
    }
    /** CSV long : une ligne par donnee, section + libelle + cinq valeurs. Photos referencees, non incorporees. */
    private function csv(Dossier $dossier,array $data): Response {
        // Ecrire avant d'envoyer les en-tetes permet une erreur propre si une valeur est illisible.
        $stream=fopen('php://temp/maxmemory:2097152','w+');
        if(!$stream) throw new \RuntimeException('Flux CSV indisponible.');
        try {
            fwrite($stream,"\xEF\xBB\xBF"); // UTF-8 / Excel francais
            $add=function(array $values)use($stream):void {
                $cells=array_map(function($v):string {
                    $text=$this->texte($v);
                    // Neutraliser les formules de tableur, y compris espaces avant le signe.
                    return preg_match('/^[\s\x00-\x20]*[=+@-]/u',$text) ? "'".$text : $text;
                },array_pad($values,7,''));
                if(fputcsv($stream,$cells,';', '"', '', "\r\n")===false) throw new \RuntimeException('Ecriture CSV impossible.');
            };
            $add(['SECTION','LIBELLE','VALEUR 1','VALEUR 2','VALEUR 3','VALEUR 4','VALEUR 5']);
            $add(['CITRAC','FICHE DOSSIER',$dossier->getNumeroOt()]);
            foreach($data['champs'] as $key=>$value) $add(['DOSSIER',$key,$value]);
            $i=$data['indicateur'];$add(['INDICATEURS','Avancement (%)',$i['pourcentage'],$i['realise'],$i['total']]);
            $add(['INDICATEURS','Delai documentaire (jours)',$i['jours'],$i['depasse']?'DELAI DEPASSE':'',$i['incoherent']?'DATES INCOHERENTES':'']);
            $s=$data['suivi'];
            if($s) {
                $add(['SUIVI','Rapport / observations',$s->getRapportEmpotage()]);$add(['SUIVI','Rapport joint',$s->getNomRapport()]);
                $add(['SUIVI','Contrat AFRICAO',$s->getContratAfricao()]);$add(['SUIVI','Port destination',$s->getPortDestination()]);
                $add(['SUIVI','Montant',$s->getTypeMontant(),$s->getMontant()]);
                foreach($s->getCertificats() as $key=>$value) $add(['CERTIFICAT',$key,is_scalar($value)?($data['statuts'][$value]??$value):$value]);
                foreach($s->getTraitements() as $key=>$value) $add(['TRAITEMENT',$key,$value]);
                foreach($s->getConteneurs() as $tc) {
                    $add(['CONTENEUR',$tc['tc']??'',$tc['typeTc']??'',$tc['plomb']??'',$tc['vrac']??false]);
                    foreach($tc['lots']??[] as $l) $add(['LOT CHARGE',$l['numero']??'',$tc['tc']??'',$l['nbCartons']??'',$l['qteExporter']??'',$l['pbExporter']??'','kg']);
                }
                foreach($s->getPhotosEmpotage()??[] as $photo) $add(['PHOTO EMPOTAGE',$photo['nom']??'',$photo['fichier']??'']);
            }
            foreach($data['formules'] as $f) {
                $e=$f->getExecution();$cv=$e?->getCodeCvDeblocage();
                $add(['FORMULE',$f->getNumero(),$e?->getNumeroCvExecution(),$e?->getPeriode(),$f->getVolume(),$f->getUtilise(),'kg']);
                $add(['CV DEBLOCAGE',$f->getNumero(),$cv?->getNumeroCv(),$cv?->getPeriode()]);
                $add(['PIECE FORMULE',$f->getNumero(),$f->getNomOriginal(),$f->getFichier()]);
            }
            foreach($data['origines'] as $o) {
                if(!$o['camions']) $add(['ORIGINE LOT',$o['lot'],'Origine non renseignee',$o['origineLibre']]);
                foreach($o['camions'] as $c) $add(['ORIGINE LOT',$o['lot'],$c['numero'],$c['immatriculation'],$c['lotSource']]);
            }
            foreach($data['documents'] as $doc) $add(['DOCUMENT',$doc->getLibelle(),$data['statuts'][$doc->getStatut()]??$doc->getStatut(),$doc->getNomOriginal()?:$doc->getFilename(),$doc->getTransmisLe(),$doc->getTransmisPar()]);
            foreach($data['historiques'] as $doc) $add(['DOCUMENT HISTORIQUE',$doc->getCodeTypeDoc()?->getLibelle(),$doc->getFilename(),$doc->getCreatedAt()]);
            foreach($data['photosHistoriques'] as $photo) $add(['PHOTO HISTORIQUE',$photo->getFilename(),$photo->getFichier()]);
            rewind($stream);
        } catch(\Throwable $e) { fclose($stream);throw $e; }
        $response=new StreamedResponse(static function()use($stream):void {try{fpassthru($stream);}finally{fclose($stream);}});
        $response->headers->set('Content-Type','text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition','attachment; filename="'.$this->nom($dossier).'.csv"');
        $response->headers->set('Cache-Control','private, no-store');$response->headers->set('X-Content-Type-Options','nosniff');return $response;
    }
    private function pdf(Dossier $dossier,array $data,PhotosEmpotage $photos): Response {
        if(!class_exists(\TCPDF::class)) throw new \RuntimeException('Installer tecnickcom/tcpdf pour exporter la fiche PDF.');
        $pdf=new class('P','mm','A4',true,'UTF-8',false) extends \TCPDF {
            public function Header() {
                $this->SetFont('dejavusans','B',15);$this->SetTextColor(74,50,38);
                $this->SetXY(15,10);$this->Cell(180,7,'CITRAC',0,1,'L');
                $this->SetFont('dejavusans','',8);$this->Cell(180,5,'FICHE DOSSIER D’EMBARQUEMENT',0,1,'L');
                $this->SetDrawColor(197,181,161);$this->Line(15,24,195,24);
            }
            public function Footer() {
                $this->SetY(-12);$this->SetFont('dejavusans','',8);$this->SetTextColor(110,100,90);
                $this->Cell(0,5,'CITRAC | Page '.$this->getAliasNumPage().' / '.$this->getAliasNbPages(),0,0,'C');
            }
        };
        $pdf->SetCreator('CITRAC GMAO');$pdf->SetAuthor('CITRAC');$pdf->SetTitle('Fiche dossier '.$dossier->getNumeroOt());
        $pdf->SetMargins(15,30,15);$pdf->SetHeaderMargin(10);$pdf->SetFooterMargin(10);$pdf->SetAutoPageBreak(true,18);
        $pdf->SetFont('dejavusans','',9);$pdf->AddPage();
        // Le gabarit reutilise toutes les donnees, tables et informations de la fiche actualisee.
        $html=$this->renderView('logistique/embarquement/export_pdf.html.twig',$data);
        $pdf->writeHTML($html,true,false,true,false,'');
        $images=$data['suivi']?->getPhotosEmpotage()??[];
        foreach($images as $photo) {
            $path=$photos->chemin((string)($photo['fichier']??''));
            if(!$path) throw new \RuntimeException('Photo d’empotage manquante : '.($photo['nom']??''));
            $size=getimagesize($path);if(!$size) throw new \RuntimeException('Photo d’empotage illisible.');
            $pdf->AddPage();$pdf->SetFont('dejavusans','B',11);$pdf->MultiCell(180,8,'Photo d’empotage — '.($photo['nom']??''));
            $scale=min(180/$size[0],210/$size[1]);$w=$size[0]*$scale;$h=$size[1]*$scale;
            $pdf->Image($path,15+(180-$w)/2,$pdf->GetY()+4,$w,$h,'JPG');
        }
        if(!$images) $pdf->writeHTML('<p>Aucune photo d’empotage.</p>');
        $bytes=$pdf->Output($this->nom($dossier).'.pdf','S');
        return new Response($bytes,200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.$this->nom($dossier).'.pdf"','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
}
