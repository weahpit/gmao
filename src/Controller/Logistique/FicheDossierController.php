<?php
namespace App\Controller\Logistique;
use App\Entity\Logistique\Dossier;
use App\Service\Logistique\{FicheDossier,PhotosEmpotage};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Response,BinaryFileResponse};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
final class FicheDossierController extends AbstractController
{
    private function dossier(string $id,EntityManagerInterface $em): Dossier {
        $ok=false;foreach(['ROLE_ADMIN','ROLE_DG','ROLE_DL','ROLE_SHIPPING','ROLE_AGENT_SHIPPING','AGENT_SHIPPING','ROLE_LOGISTIQUE','ROLE_LOGISTIQU'] as $role)if($this->isGranted($role))$ok=true;if(!$ok)throw $this->createAccessDeniedException();
        $d=Uuid::isValid($id)?$em->find(Dossier::class,Uuid::fromString($id)):null;if(!$d)throw $this->createNotFoundException();return $d;
    }
    #[Route('/logistique/embarquements/{id}/fiche',name:'logistique_dossier_fiche',methods:['GET'])]
    public function fiche(string $id,EntityManagerInterface $em,FicheDossier $service): Response {return $this->render('logistique/embarquement/fiche.html.twig',$service->donnees($this->dossier($id,$em)));}
    #[Route('/logistique/embarquements/{id}/photos/{nom}',name:'logistique_photo_empotage',methods:['GET'])]
    public function photo(string $id,string $nom,EntityManagerInterface $em,PhotosEmpotage $photos): BinaryFileResponse {
        $d=$this->dossier($id,$em);$s=$em->getRepository(\App\Entity\Logistique\SuiviEmbarquement::class)->findOneBy(['dossier'=>$d]);
        if(!in_array($nom,array_column($s?->getPhotosEmpotage()??[],'fichier'),true)||!($p=$photos->chemin($nom)))throw $this->createNotFoundException();
        $r=$this->file($p,$nom,'inline');$r->headers->set('Content-Type','image/jpeg');$r->headers->set('Cache-Control','private, no-store');$r->headers->set('X-Content-Type-Options','nosniff');return $r;
    }
    #[Route('/logistique/embarquements/{id}/export/{format}',name:'logistique_dossier_export',requirements:['format'=>'pdf|xlsx'],methods:['GET'])]
    public function exporter(string $id,string $format,EntityManagerInterface $em,FicheDossier $service,PhotosEmpotage $photos): Response {
        $data=$service->donnees($this->dossier($id,$em));$d=$data['dossier'];$nom='CITRAC-dossier-'.preg_replace('/[^a-zA-Z0-9_-]/','_',$d->getNumeroOt());
        // Photos embarquées, sans accès réseau dans le moteur PDF.
        $images=[];foreach($data['suivi']?->getPhotosEmpotage()??[] as $photo){$p=$photos->chemin($photo['fichier']);if($p)$images[]=['nom'=>$photo['nom'],'chemin'=>$p];} $data['images']=$images;
        if($format==='pdf') {
            if(!class_exists(\Dompdf\Dompdf::class))return new Response('Export PDF : installez dompdf/dompdf (voir LISEZ-MOI).',503);
            $options=new \Dompdf\Options();$options->set('isRemoteEnabled',false);$options->set('isPhpEnabled',false);$options->set('chroot',dirname(__DIR__,3).'/var/export');$options->set('defaultFont','DejaVu Sans');
            $pdf=new \Dompdf\Dompdf($options);$pdf->loadHtml($this->renderView('logistique/embarquement/export_pdf.html.twig',$data),'UTF-8');$pdf->setPaper('A4');$pdf->render();
            return new Response($pdf->output(),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.$nom.'.pdf"','Cache-Control'=>'private, no-store']);
        }
        if(!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class))return new Response('Export Excel : installez phpoffice/phpspreadsheet (voir LISEZ-MOI).',503);
        $book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$sheet=$book->getActiveSheet();$sheet->setTitle('Dossier');$sheet->mergeCells('A1:D1')->setCellValue('A1','CITRAC — FICHE DOSSIER '.$d->getNumeroOt());$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $row=3;$add=function(array $values)use($sheet,&$row){foreach(array_values($values) as $col=>$value)$sheet->setCellValueExplicit([$col+1,$row],(string)($value??''),\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);$row++;};
        foreach($data['champs'] as $key=>$value)$add([$key,$value]);
        $add(['AVANCEMENT',$data['indicateur']['pourcentage'].'%']);$add(['DÉLAI DOCUMENTAIRE',$data['indicateur']['jours'],'jours']);
        $s=$data['suivi'];if($s){$add(['RAPPORT / OBSERVATIONS',$s->getRapportEmpotage()]);$add(['CONTRAT AFRICAO',$s->getContratAfricao()]);$add(['PORT DESTINATION',$s->getPortDestination()]);$add(['MONTANT',$s->getTypeMontant(),$s->getMontant()]);foreach($s->getCertificats() as $key=>$value)$add(['CERTIFICAT',$key,$value]);foreach($s->getTraitements() as $key=>$value)$add(['TRAITEMENT',$key,$value]);$add(['CONTENEUR / PLOMB','LOT','SACS','POIDS NET KG']);foreach($s->getConteneurs() as $tc)foreach($tc['lots']??[] as $lot)$add([$tc['tc'].' / '.$tc['plomb'],$lot['numero'],$lot['nbCartons'],$lot['qteExporter']]);}
        $add(['FORMULE','CV EXÉCUTION','PÉRIODE','VOLUME / UTILISÉ KG']);foreach($data['formules'] as $f)$add([$f->getNumero(),$f->getExecution()->getNumeroCvExecution(),$f->getExecution()->getPeriode(),$f->getVolume().' / '.$f->getUtilise()]);
        $add(['ORIGINE LOT','N° CAMIONS','IMMATRICULATION','LOT SOURCE']);foreach($data['origines'] as $o){if(!$o['camions'])$add([$o['lot'],'Non renseignée',$o['origineLibre']]);foreach($o['camions'] as $c)$add([$o['lot'],$c['numero'],$c['immatriculation'],$c['lotSource']]);}
        $add(['DOCUMENT','STATUT','FICHIER','TRANSMIS']);foreach($data['documents'] as $doc)$add([$doc->getLibelle(),$doc->getStatut(),$doc->getNomOriginal(),$doc->getTransmisLe()?->format('d/m/Y H:i')]);
        foreach($data['historiques'] as $doc)$add(['DOCUMENT HISTORIQUE',$doc->getCodeTypeDoc()?->getLibelle(),$doc->getFilename()]);
        $add(['PHOTOS EMPOTAGE']);foreach($images as $image){$add([$image['nom']]);$draw=new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();$draw->setPath($image['chemin']);$draw->setCoordinates('A'.$row);$draw->setHeight(150);$draw->setWorksheet($sheet);$sheet->getRowDimension($row)->setRowHeight(120);$row++;}
        foreach(['A'=>32,'B'=>42,'C'=>28,'D'=>42] as $col=>$width)$sheet->getColumnDimension($col)->setWidth($width);$sheet->getStyle('A1:D'.$row)->getAlignment()->setWrapText(true);$sheet->freezePane('A3');$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1,1);$sheet->getHeaderFooter()->setOddHeader('&CCITRAC — Dossier '.$d->getNumeroOt());
        ob_start();try{(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output');$bytes=ob_get_contents();}finally{ob_end_clean();$book->disconnectWorksheets();}
        return new Response($bytes,200,['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Content-Disposition'=>'attachment; filename="'.$nom.'.xlsx"','Cache-Control'=>'private, no-store']);
    }
}
