<?php
namespace App\Service\Logistique;
use Symfony\Component\HttpFoundation\File\UploadedFile;
final class PhotosEmpotage
{
    public function dossier(): string { return dirname(__DIR__,3).'/var/export/photos'; }
    public function chemin(string $nom): ?string {
        if (!preg_match('/^[a-f0-9]{32}\.jpg$/D',$nom)) return null;
        $p=$this->dossier().'/'.$nom; return is_file($p) ? $p : null;
    }
    public function supprimer(string $nom): void { if ($p=$this->chemin($nom)) unlink($p); }
    public function ajouter(array $fichiers, array $existantes): array {
        $fichiers=array_values(array_filter($fichiers,static fn($f)=>$f instanceof UploadedFile && $f->getError()!==UPLOAD_ERR_NO_FILE));
        if (count($fichiers)+count($existantes)>25) throw new \InvalidArgumentException('25 photos maximum par dossier, photos existantes comprises.');
        $nouvelles=[];
        try { foreach ($fichiers as $f) $nouvelles[]=$this->convertir($f); }
        catch (\Throwable $e) { foreach ($nouvelles as $p) $this->supprimer($p['fichier']); throw $e; }
        return $nouvelles;
    }
    private function convertir(UploadedFile $f): array {
        if (!$f->isValid()) throw new \InvalidArgumentException('Photo non reçue : '.$f->getErrorMessage());
        if ($f->getSize()>12000000) throw new \InvalidArgumentException('Photo source : 12 Mo maximum.');
        if (!extension_loaded('gd')) throw new \RuntimeException('Activez l’extension PHP GD pour convertir les photos.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($f->getPathname());
        $loaders=['image/jpeg'=>'imagecreatefromjpeg','image/png'=>'imagecreatefrompng','image/webp'=>'imagecreatefromwebp'];
        if (!isset($loaders[$mime]) || !function_exists($loaders[$mime])) throw new \InvalidArgumentException('Photos acceptées : JPEG, PNG, WEBP.');
        $size=getimagesize($f->getPathname());
        if (!$size || $size[0]*$size[1]>24000000) throw new \InvalidArgumentException('Photo limitée à 24 millions de pixels.');
        $src=$loaders[$mime]($f->getPathname());
        if (!$src) throw new \InvalidArgumentException('Image illisible.');
        try {
            if ($mime==='image/jpeg' && function_exists('exif_read_data')) {
                $exif=@exif_read_data($f->getPathname()); $orientation=(int) ($exif['Orientation'] ?? 1);
                if (in_array($orientation,[2,4,5,7],true)) imageflip($src,IMG_FLIP_HORIZONTAL);
                $angle=[3=>180,4=>180,5=>90,6=>-90,7=>-90,8=>90][$orientation] ?? 0;
                if ($angle) { $rot=imagerotate($src,$angle,0); imagedestroy($src); $src=$rot; }
            }
            $w=imagesx($src);$h=imagesy($src);$scale=min(1,1600/max($w,$h));$data='';
            for ($attempt=0;$attempt<12;$attempt++) {
                $nw=max(1,(int)($w*$scale));$nh=max(1,(int)($h*$scale));$dst=imagecreatetruecolor($nw,$nh);
                imagefill($dst,0,0,imagecolorallocate($dst,255,255,255));imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);
                for ($q=85;$q>=35;$q-=10) { ob_start();imagejpeg($dst,null,$q);$data=ob_get_clean();if (strlen($data)<200000) break; }
                imagedestroy($dst); if (strlen($data)<200000) break;$scale*=.75;
            }
            if ($data==='' || strlen($data)>=200000) throw new \RuntimeException('Impossible de réduire la photo sous 200 Ko.');
            $dir=$this->dossier();if (!is_dir($dir) && !mkdir($dir,0750,true) && !is_dir($dir)) throw new \RuntimeException('Dossier photos non inscriptible.');
            $nom=bin2hex(random_bytes(16)).'.jpg';if (file_put_contents($dir.'/'.$nom,$data,LOCK_EX)!==strlen($data)) throw new \RuntimeException('Écriture de photo impossible.');
            return ['fichier'=>$nom,'nom'=>mb_substr(basename($f->getClientOriginalName()),0,255),'octets'=>strlen($data)];
        } finally { imagedestroy($src); }
    }
}
