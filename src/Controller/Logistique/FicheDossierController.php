<?php
namespace App\Controller\Logistique;
use App\Entity\Logistique\Dossier;
use App\Service\Logistique\{FicheDossier,PhotosEmpotage};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Response,BinaryFileResponse};
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
    public function exporter(string $id,string $format,EntityManagerInterface $em,FicheDossier $service,PhotosEmpotage $photos,LoggerInterface $logger,#[Autowire('%kernel.debug%')] bool $debug=false): Response {
        $dossier=$this->dossier($id,$em); // 403/404 gérés ici, hors du try
        // Les photos (jusqu'à 25) consomment de la mémoire dans Dompdf / PhpSpreadsheet.
        @ini_set('memory_limit','512M');@set_time_limit(120);
        try {
            return $format==='pdf'?$this->pdf($dossier,$service,$photos):$this->xlsx($dossier,$service,$photos);
        } catch(\Throwable $e) {
            $ref=bin2hex(random_bytes(4));
            $logger->error('Export fiche dossier '.$format.' en échec ['.$ref.'] : '.$e->getMessage(),['exception'=>$e]);
            $this->addFlash('danger','Export '.strtoupper($format).' impossible (réf. '.$ref.')'.($debug?' : '.$e->getMessage():'. Consultez var/log.'));
            return $this->redirectToRoute('logistique_dossier_fiche',['id'=>$id]);
        }
    }
    private function nom(Dossier $d): string { return 'CITRAC-dossier-'.preg_replace('/[^a-zA-Z0-9_-]/','_',(string)$d->getNumeroOt()); }
    /** @return list<array{nom:string,chemin:string}> */
    private function images(array $data,PhotosEmpotage $photos): array {
        $images=[];foreach($data['suivi']?->getPhotosEmpotage()??[] as $photo){$p=$photos->chemin((string)($photo['fichier']??''));if($p)$images[]=['nom'=>(string)($photo['nom']??''),'chemin'=>$p];}
        return $images;
    }
    private function texte(mixed $v): string {
        return match(true){
            $v===null=>'',
            is_bool($v)=>$v?'OUI':'NON',
            is_scalar($v)=>(string)$v,
            $v instanceof \DateTimeInterface=>$v->format('d/m/Y H:i'),
            $v instanceof \Stringable=>(string)$v,
            is_array($v)=>(string)json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            default=>'',
        };
    }
    private function pdf(Dossier $dossier,FicheDossier $service,PhotosEmpotage $photos): Response {
        if(!class_exists(\Dompdf\Dompdf::class))throw new \RuntimeException('dompdf/dompdf n’est pas installé : composer require dompdf/dompdf');
        $data=$service->donnees($dossier);$images=$this->images($data,$photos);
        // Images intégrées en base64 : évite les soucis de chroot / chemins Windows dans Dompdf.
        foreach($images as &$img){$img['uri']='data:image/jpeg;base64,'.base64_encode((string)file_get_contents($img['chemin']));}unset($img);
        $data['images']=$images;
        $options=new \Dompdf\Options();$options->set('isRemoteEnabled',false);$options->set('isPhpEnabled',false);$options->set('defaultFont','DejaVu Sans');
        $pdf=new \Dompdf\Dompdf($options);$pdf->loadHtml($this->renderView('logistique/embarquement/export_pdf.html.twig',$data),'UTF-8');$pdf->setPaper('A4');$pdf->render();
        return new Response($pdf->output(),200,['Content-Type'=>'application/pdf','Content-Disposition'=>'attachment; filename="'.$this->nom($dossier).'.pdf"','Cache-Control'=>'private, no-store']);
    }
    private function xlsx(Dossier $dossier,FicheDossier $service,PhotosEmpotage $photos): Response {
        if(!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class))throw new \RuntimeException('phpoffice/phpspreadsheet n’est pas installé : composer require phpoffice/phpspreadsheet');
        $data=$service->donnees($dossier);$d=$data['dossier'];$images=$this->images($data,$photos);$t=fn(mixed $v)=>$this->texte($v);
        $book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();$sheet=$book->getActiveSheet();$sheet->setTitle('Dossier');$sheet->mergeCells('A1:D1');$sheet->setCellValue('A1','CITRAC — FICHE DOSSIER '.$d->getNumeroOt());$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $row=3;$add=function(array $values)use($sheet,&$row,$t){foreach(array_values($values) as $col=>$value)$sheet->setCellValueExplicit([$col+1,$row],$t($value),\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);$row++;};
        foreach($data['champs'] as $key=>$value)$add([$key,$value]);
        $add(['AVANCEMENT',$data['indicateur']['pourcentage'].'%']);$add(['DÉLAI DOCUMENTAIRE',$data['indicateur']['jours'],'jours']);
        $s=$data['suivi'];if($s){$add(['RAPPORT / OBSERVATIONS',$s->getRapportEmpotage()]);$add(['CONTRAT AFRICAO',$s->getContratAfricao()]);$add(['PORT DESTINATION',$s->getPortDestination()]);$add(['MONTANT',$s->getTypeMontant(),$s->getMontant()]);foreach($s->getCertificats() as $key=>$value)$add(['CERTIFICAT',$key,$value]);foreach($s->getTraitements() as $key=>$value)$add(['TRAITEMENT',$key,$value]);$add(['CONTENEUR / PLOMB','LOT','SACS','POIDS NET KG']);foreach($s->getConteneurs() as $tc)foreach($tc['lots']??[] as $lot)$add([($tc['tc']??'').' / '.($tc['plomb']??''),$lot['numero']??'',$lot['nbCartons']??'',$lot['qteExporter']??'']);}
        $add(['FORMULE','CV EXÉCUTION','PÉRIODE','VOLUME / UTILISÉ KG']);foreach($data['formules'] as $f)$add([$f->getNumero(),$f->getExecution()?->getNumeroCvExecution(),$f->getExecution()?->getPeriode(),$f->getVolume().' / '.$f->getUtilise()]);
        $add(['ORIGINE LOT','N° CAMIONS','IMMATRICULATION','LOT SOURCE']);foreach($data['origines'] as $o){if(!$o['camions'])$add([$o['lot'],'Non renseignée',$o['origineLibre']]);foreach($o['camions'] as $c)$add([$o['lot'],$c['numero'],$c['immatriculation'],$c['lotSource']]);}
        $add(['DOCUMENT','STATUT','FICHIER','TRANSMIS']);foreach($data['documents'] as $doc)$add([$doc->getLibelle(),$doc->getStatut(),$doc->getNomOriginal(),$doc->getTransmisLe()?->format('d/m/Y H:i')]);
        foreach($data['historiques'] as $doc)$add(['DOCUMENT HISTORIQUE',$doc->getCodeTypeDoc()?->getLibelle(),$doc->getFilename()]);
        $add(['PHOTOS EMPOTAGE']);foreach($images as $image){$add([$image['nom']]);$draw=new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();$draw->setPath($image['chemin']);$draw->setCoordinates('A'.$row);$draw->setHeight(150);$draw->setWorksheet($sheet);$sheet->getRowDimension($row)->setRowHeight(120);$row++;}
        foreach(['A'=>32,'B'=>42,'C'=>28,'D'=>42] as $col=>$width)$sheet->getColumnDimension($col)->setWidth($width);$sheet->getStyle('A1:D'.$row)->getAlignment()->setWrapText(true);$sheet->freezePane('A3');$sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1,1);$sheet->getHeaderFooter()->setOddHeader('&CCITRAC — Dossier '.$d->getNumeroOt());
        ob_start();try{(new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output');$bytes=ob_get_contents();}finally{ob_end_clean();$book->disconnectWorksheets();}
        return new Response($bytes,200,['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Content-Disposition'=>'attachment; filename="'.$this->nom($dossier).'.xlsx"','Cache-Control'=>'private, no-store']);
    }
}
