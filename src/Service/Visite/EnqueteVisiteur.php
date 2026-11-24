<?php
namespace App\Service\Visite;

final class EnqueteVisiteur
{
    public const QUESTIONS = [
        'typhoide'=>'FIEVRE TYPHOÏDE', 'cholera'=>'CHOLERA', 'tuberculose'=>'TUBERCULOSE',
        'rhume_grippe'=>'RHUME / GRIPPE', 'ebola'=>'FIEVRE EBOLA', 'hepatite'=>'HEPATITE A, B, C',
        'meningite'=>'MENINGITE', 'ist_vih'=>'IST VIH/SIDA', 'coronavirus'=>'CORONA VIRUS', 'allergene'=>'ALERGENE',
    ];

    public static function reponses(mixed $valeur, bool $complete = true): array
    {
        if (!is_array($valeur)) throw new \InvalidArgumentException('Enquete sanitaire invalide.');
        $resultat=[];
        foreach (self::QUESTIONS as $cle=>$libelle) {
            $v=$valeur[$cle] ?? '';
            if (!in_array($v,['OUI','NON'],true)) {
                if (!$complete && $v==='') continue;
                throw new \InvalidArgumentException('Choisissez OUI ou NON pour : '.$libelle.'.');
            }
            $resultat[$cle]=$v;
        }
        if (array_diff(array_keys($valeur),array_keys(self::QUESTIONS))) throw new \InvalidArgumentException('Question sanitaire inconnue.');
        return $resultat;
    }

    /** Traits vectoriels seulement : pas de fichier, HTML, base64 ou contenu executable. */
    public static function signature(mixed $valeur): ?array
    {
        if ($valeur === '' || $valeur === null || $valeur === []) return null;
        if (is_string($valeur)) {
            if (strlen($valeur)>500000) throw new \InvalidArgumentException('Signature trop volumineuse. Effacez et recommencez.');
            try { $valeur=json_decode($valeur,true,8,JSON_THROW_ON_ERROR); }
            catch (\JsonException) {throw new \InvalidArgumentException('Signature illisible. Effacez et recommencez.');}
        }
        if (!is_array($valeur) || !array_is_list($valeur) || count($valeur)>300) throw new \InvalidArgumentException('Signature invalide.');
        $resultat=[]; $nombre=0;
        foreach ($valeur as $trait) {
            if (!is_array($trait) || !array_is_list($trait)) throw new \InvalidArgumentException('Trait de signature invalide.');
            $points=[];
            foreach ($trait as $p) {
                if (!is_array($p) || !array_is_list($p) || count($p)!==2 || (!is_int($p[0]) && !is_float($p[0])) || (!is_int($p[1]) && !is_float($p[1]))) throw new \InvalidArgumentException('Point de signature invalide.');
                if (!is_finite((float) $p[0]) || !is_finite((float) $p[1]) || $p[0]<0 || $p[0]>900 || $p[1]<0 || $p[1]>240 || ++$nombre>20000) throw new \InvalidArgumentException('Dimensions ou taille de signature invalides.');
                $points[]=[round($p[0],2),round($p[1],2)];
            }
            if (count($points)>1) $resultat[]=$points;
        }
        return $resultat ?: null;
    }

    public static function image(array $traits): string
    {
        $traits=self::signature($traits) ?? [];
        $svg='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 240" width="900" height="240"><rect width="900" height="240" fill="white"/>';
        foreach ($traits as $trait) {
            $points=implode(' ',array_map(static fn(array $p):string=>$p[0].','.$p[1],$trait));
            $svg.='<polyline fill="none" stroke="#243642" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" points="'.$points.'"/>';
        }
        return $svg.'</svg>';
    }
}
