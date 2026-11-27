<?php

declare(strict_types=1);

namespace App\Controller\Logistique;

use App\Entity\Logistique\{Dossier, CvDeblocage, CvExecution, FormuleExport, SuiviEmbarquement, Proprietaire, Client, Navire, Destination, ModeleDoc, DetailsModeleDoc, DocType, DocsDossier, Banque, Transitaire, CompagnieNavigation};
use App\Entity\Production\{Produit, Lot};
use App\Service\Logistique\{ReglesCvs, DocumentsExport, StockageDocumentsExport, EnregistrerEmbarquement};
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response, JsonResponse, BinaryFileResponse};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_USER')]
final class OperationsLogistiquesController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em, private ReglesCvs $cvs, private DocumentsExport $documents, private StockageDocumentsExport $stockage, private EnregistrerEmbarquement $enregistrement, private \App\Service\Logistique\PhotosEmpotage $photos, private \Psr\Log\LoggerInterface $logger) {}

    private function autoriser(): void
    {
        foreach (['ROLE_ADMIN','ROLE_DG','AGENT_SHIPPING','ROLE_AGENT_SHIPPING','ROLE_LOGISTIQUE','ROLE_LOGISTIQU','ROLE_SHIPPING','ROLE_DL'] as $role) if ($this->isGranted($role)) return;
        throw $this->createAccessDeniedException('Acces reserve aux operations logistiques.');
    }

    private function jeton(Request $r): void
    {
        if (!$this->isCsrfTokenValid('operations_logistiques', (string) $r->request->get('_token'))) throw $this->createAccessDeniedException('Formulaire expire : rechargez la page.');
    }

    private function trouver(string $classe, mixed $id): object
    {
        if (!is_string($id) || !Uuid::isValid($id)) throw new \InvalidArgumentException('Selection obligatoire ou invalide.');
        $entite = $this->em->find($classe, Uuid::fromString($id));
        if (!$entite) throw $this->createNotFoundException('Element introuvable.');
        return $entite;
    }

    #[Route('/logistique/cvs-formules', name: 'logistique_cvs_formules', methods: ['GET','POST'])]
    public function cvsFormules(Request $request): Response
    {
        $this->autoriser();
        if ($request->isMethod('POST')) {
            $this->jeton($request);
            $nouveauFichier = null;
            try {
                $action = (string) $request->request->get('action');
                if ($action === 'deblocage') {
                    $this->cvs->creerDeblocage((string) $request->request->get('numero'), $request->request->get('volume'), $this->trouver(Proprietaire::class, $request->request->get('proprietaire')), (string) $request->request->get('periode'));
                } elseif ($action === 'execution') {
                    $this->cvs->creerExecution($this->trouver(CvDeblocage::class, $request->request->get('deblocage')), (string) $request->request->get('numero'), $request->request->get('volume'), (string) $request->request->get('periode'));
                } elseif ($action === 'volume_execution') {
                    $this->cvs->modifierVolumeExecution($this->trouver(CvExecution::class, $request->request->get('execution')), $request->request->get('volume'));
                } elseif ($action === 'formule') {
                    $fichier = $request->files->get('fichier');
                    if ($fichier instanceof UploadedFile && $fichier->getError() !== UPLOAD_ERR_NO_FILE) $nouveauFichier = $this->stockage->enregistrer($fichier);
                    $this->cvs->creerFormule($this->trouver(CvExecution::class, $request->request->get('execution')), (string) $request->request->get('numero'), $request->request->get('volume'), $nouveauFichier);
                    $nouveauFichier = null;
                } elseif ($action === 'fichier_formule') {
                    $fichier = $request->files->get('fichier');
                    if (!$fichier instanceof UploadedFile || $fichier->getError() === UPLOAD_ERR_NO_FILE) throw new \InvalidArgumentException('Selectionnez le fichier de la formule.');
                    $formule = $this->trouver(FormuleExport::class, $request->request->get('formule'));
                    if ($formule->getDossier()?->isCloture()) throw new \InvalidArgumentException('Rouvrez le dossier avant de remplacer sa formule.');
                    $nouveauFichier = $this->stockage->enregistrer($fichier);
                    $ancien = $formule->getFichier();
                    $formule->setFichier($nouveauFichier['fichier'])->setNomOriginal($nouveauFichier['nom']);
                    $this->em->flush();
                    $nouveauFichier = null;
                    if ($ancien) $this->stockage->supprimer($ancien);
                } else { throw new \InvalidArgumentException('Action inconnue.'); }
                if ($request->isXmlHttpRequest()) return $this->json(['message'=>'Enregistrement effectué. Les soldes sont recalculés.']);
                $this->addFlash('success', 'Enregistrement effectue. Les soldes sont recalcules.');
            } catch (\Throwable $e) {
                if ($nouveauFichier) $this->stockage->supprimer($nouveauFichier['fichier']);
                $reference = bin2hex(random_bytes(6));
                $validation = $e instanceof \InvalidArgumentException;
                $message = $validation && trim($e->getMessage()) !== ''
                    ? $e->getMessage()
                    : 'Erreur technique pendant l’enregistrement. Reference : '.$reference.'. Consultez le journal serveur pour cette reference ; votre saisie est conservee.';
                $this->logger->error('Echec enregistrement embarquement {reference}', ['reference'=>$reference,'exception'=>$e]);
                if ($request->isXmlHttpRequest()) return $this->json(['message'=>$message,'code'=>$validation ? 'VALIDATION_DOSSIER' : 'ERREUR_TECHNIQUE','reference'=>$reference], $validation ? 422 : 500);
                $this->addFlash('danger', $message);
            }
            return $this->redirectToRoute('logistique_cvs_formules');
        }
        $deblocages = $this->em->getRepository(CvDeblocage::class)->findBy([], ['numero_cv'=>'ASC']);
        $executions = $this->em->getRepository(CvExecution::class)->findBy([], ['numero_cv_execution'=>'ASC']);
        $bilansDeb = []; $bilansExe = [];
        foreach ($deblocages as $d) $bilansDeb[(string) $d->getId()] = $this->cvs->bilanDeblocage($d);
        foreach ($executions as $e) $bilansExe[(string) $e->getId()] = $this->cvs->bilanExecution($e);
        return $this->render('logistique/gestion/cvs_formules.html.twig', [
            'deblocages'=>$deblocages, 'executions'=>$executions, 'bilansDeb'=>$bilansDeb, 'bilansExe'=>$bilansExe,
            'formules'=>$this->em->getRepository(FormuleExport::class)->findBy([], ['numero'=>'ASC']),
            'proprietaires'=>$this->em->getRepository(Proprietaire::class)->findBy([], ['denomination'=>'ASC']),
        ]);
    }

    #[Route('/logistique/enregistrement-dossier', name: 'enregistrement_embarquement', methods: ['GET','POST'])]
    public function nouveau(Request $request): Response { return $this->formulaire($request, null); }

    #[Route('/logistique/embarquements/{id}/gestion', name: 'logistique_dossier_gestion', methods: ['GET','POST'])]
    public function modifier(Request $request, string $id): Response { return $this->formulaire($request, $this->trouver(Dossier::class, $id)); }

    private function formulaire(Request $request, ?Dossier $dossier): Response
    {
        $this->autoriser(); $donnees=[]; $tc=[]; $affectations=[];
        $suivi = $dossier ? $this->em->getRepository(SuiviEmbarquement::class)->findOneBy(['dossier'=>$dossier]) : null;
        if ($dossier) {
            $donnees = ['numero_ot'=>$dossier->getNumeroOt(), 'client'=>(string) $dossier->getClient()?->getId(), 'proprietaire'=>(string) $dossier->getProprietaire()?->getId(), 'produit'=>(string) $dossier->getProduit()?->getId(), 'navire'=>(string) $dossier->getCodeNavire()?->getId(), 'destination'=>(string) $dossier->getDestination()?->getId(), 'rapport_empotage'=>$suivi?->getRapportEmpotage(), 'contrat_africao'=>$suivi?->getContratAfricao(), 'port_destination'=>$suivi?->getPortDestination(), 'type_montant'=>$suivi?->getTypeMontant(), 'montant'=>$suivi?->getMontant()];
            foreach (['booking'=>'getBooking','port_embarquement'=>'getPortEmbarquement','numero_bl'=>'getNumeroBl'] as $cle=>$methode) $donnees[$cle]=$dossier->$methode();
            foreach (['banque_nantissement'=>'getBanqueNantissement','transitaire'=>'getTransitaire','compagnie_navigation'=>'getCompagnieNavigation'] as $cle=>$methode) $donnees[$cle]=(string) $dossier->$methode()?->getId();
            foreach (['date_ot'=>'getDateOt','date_bl'=>'getDateBl','date_empotage'=>'getDateEmpotage','date_relevage'=>'getDateRelevage','date_embarquement'=>'getDateEmbarquement','eta'=>'getEta','date_remise'=>'getRemiseDoc'] as $cle=>$methode) $donnees[$cle]=$dossier->$methode()?->format('Y-m-d');
            foreach ($suivi?->getCertificats() ?? [] as $cle=>$valeur) $donnees['cert_'.$cle]=$valeur;
            foreach ($suivi?->getTraitements() ?? [] as $cle=>$valeur) $donnees['options-'.$cle]=$valeur;
            $tc=$suivi?->getConteneurs() ?? [];
            $affectations = $suivi?->getFormulesPreparees() ?? [];
            if (!$affectations) foreach ($this->em->getRepository(FormuleExport::class)->findBy(['dossier'=>$dossier]) as $f) $affectations[]=['id'=>(string) $f->getId(), 'volume'=>$f->getUtilise()];
        }
        if ($request->isMethod('POST')) {
            // PHP vide POST et FILES si post_max_size est depasse : ce n'est pas un CSRF expire.
            $taille = (int) $request->server->get('CONTENT_LENGTH', 0);
            $limiteTexte = trim((string) ini_get('post_max_size'));
            $limite = (float) $limiteTexte;
            $unite = strtolower(substr($limiteTexte, -1));
            $limite *= match ($unite) { 'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1 };
            if ($limite > 0 && $taille > $limite) {
                $message = sprintf('Envoi trop volumineux : %.2f Mo envoyes ; limite totale PHP post_max_size = %s. Reduisez les fichiers ou faites augmenter cette limite sur le serveur.', $taille / 1048576, $limiteTexte);
                if ($request->isXmlHttpRequest()) return $this->json(['message'=>$message,'code'=>'ENVOI_TROP_VOLUMINEUX'], 413);
                throw new \Symfony\Component\HttpKernel\Exception\HttpException(413, $message);
            }
            if (!$this->isCsrfTokenValid('operations_logistiques', (string) $request->request->get('_token'))) {
                if ($request->isXmlHttpRequest()) return $this->json(['message'=>'Formulaire expire. Votre saisie est conservee ; le jeton doit etre renouvele.','code'=>'FORMULAIRE_EXPIRE'], 403);
                $this->jeton($request); // maintien de la protection pour un POST classique
            }
            $donnees=$request->request->all(); $nouveauFichier=null; $photosAjoutees=[]; $photosRetirees=[];
            try {
                $tc=json_decode((string) $request->request->get('conteneurs_json','[]'), true, 32, JSON_THROW_ON_ERROR);
                $affectations=$request->request->all('formules');
                if (!is_array($tc)) throw new \InvalidArgumentException('Conteneurs invalides.');
                $f=$request->files->get('rapport_fichier');
                if ($f instanceof UploadedFile && $f->getError() !== UPLOAD_ERR_NO_FILE) {
                    $nouveauFichier=$this->stockage->enregistrer($f);
                    $donnees['_rapport_fichier']=$nouveauFichier['fichier']; $donnees['_rapport_nom']=$nouveauFichier['nom'];
                }
                $existantes=$suivi?->getPhotosEmpotage() ?? [];
                $retirer=$request->request->all('retirer_photos');
                $conservees=[];
                foreach ($existantes as $photo) { if (in_array($photo['fichier'],$retirer,true)) $photosRetirees[]=$photo; else $conservees[]=$photo; }
                $photosAjoutees=$this->photos->ajouter($request->files->all('photos_empotage'),$conservees);
                $donnees['_photos_empotage']=array_merge($conservees,$photosAjoutees);
                $ancien=$suivi?->getFichierRapport();
                $enregistre=$this->enregistrement->enregistrer($donnees, $tc, $affectations, (string) $this->getUser()->getUserIdentifier(), $dossier);
                if ($nouveauFichier && $ancien) $this->stockage->supprimer($ancien);
                foreach ($photosRetirees as $photo) $this->photos->supprimer($photo['fichier']);
                if ($request->isXmlHttpRequest()) return $this->json(['message'=>'Dossier enregistré.','redirection'=>$this->generateUrl('logistique_suivi_documents',['id'=>$enregistre->getId()])]);
                $this->addFlash('success', 'Dossier enregistre : conteneurs, consommation des CV/formules et suivi documentaire.');
                return $this->redirectToRoute('logistique_suivi_documents', ['id'=>$enregistre->getId()]);
            } catch (\Throwable $e) {
                if ($nouveauFichier) $this->stockage->supprimer($nouveauFichier['fichier']);
                foreach ($photosAjoutees as $photo) $this->photos->supprimer($photo['fichier']);
                unset($donnees['_photos_empotage']);
                // La transaction a deja ete annulee : on redirige pour repartir d'un EM propre.
                $request->getSession()->set('logistique_saisie', ['donnees'=>$donnees, 'tc'=>$tc, 'affectations'=>$affectations]);
                if ($request->isXmlHttpRequest()) return $this->json(['message'=>$e->getMessage()],422);
                $this->addFlash('danger', $e->getMessage());
                return $dossier ? $this->redirectToRoute('logistique_dossier_gestion',['id'=>$dossier->getId()]) : $this->redirectToRoute('enregistrement_embarquement');
            }
        }
        if ($request->getSession()->has('logistique_saisie')) {
            $s=$request->getSession()->remove('logistique_saisie');
            $donnees=$s['donnees']; $tc=$s['tc']; $affectations=$s['affectations'];
            unset($donnees['_rapport_fichier'],$donnees['_rapport_nom']);
        }
        $formules=$this->em->getRepository(FormuleExport::class)->findBy([], ['numero'=>'ASC']); $disponibles=[];
        foreach ($formules as $f) if (!$f->getDossier() || ($dossier && (string) $f->getDossier()->getId()===(string) $dossier->getId())) $disponibles[]=$f;
        $idsReserves=[]; foreach ($suivi?->getConteneurs() ?? [] as $ct) foreach ($ct['lots'] ?? [] as $l) $idsReserves[]=$l['id'];
        $qbLots=$this->em->getRepository(Lot::class)->createQueryBuilder('l')->leftJoin('l.code_produit','p')->addSelect('p');
        $qbLots->where('COALESCE(l.poids_net_dispo,l.pn_th,0) > 0');
        if ($idsReserves) $qbLots->orWhere('l.id IN (:ids)')->setParameter('ids',array_map(static fn($id)=>Uuid::fromString($id),array_unique($idsReserves)));
        $lots=$qbLots->orderBy('l.numero_lot','ASC')->getQuery()->getResult(); $lotsDonnees=[];
        foreach ($lots as $lot) $lotsDonnees[]=['id'=>(string) $lot->getId(), 'numero'=>$lot->getNumeroLot(), 'produit'=>(string) $lot->getCodeProduit()?->getId(), 'pn'=>$lot->getPoidsNetDispo() ?? $lot->getPnTh() ?? 0, 'pnReference'=>$lot->getPnTh() ?? 0, 'pb'=>$lot->getPbTht() ?? 0, 'colis'=>$lot->getSacsRestants() ?? (int) ($lot->getNbSacs() ?? 0)];
        return $this->render('logistique/embarquement/index.html.twig', [
            'dossier'=>$dossier,'suivi'=>$suivi,'donnees'=>$donnees,'conteneurs'=>$tc,'affectations'=>$affectations,'lotsDonnees'=>$lotsDonnees,
            'reservationsOriginales'=>$suivi?->getConteneurs() ?? [],
            'destinations'=>$this->em->getRepository(Destination::class)->findBy([],['denomination'=>'ASC']),
            'proprietaires'=>$this->em->getRepository(Proprietaire::class)->findBy([],['denomination'=>'ASC']),
            'produits'=>$this->em->getRepository(Produit::class)->findBy([],['libelle'=>'ASC']),
            'clients'=>$this->em->getRepository(Client::class)->findBy([],['nom_client'=>'ASC']),
            'navires'=>$this->em->getRepository(Navire::class)->findBy([],['nom'=>'ASC']),
            'banques'=>$this->em->getRepository(Banque::class)->findBy([],['libelle'=>'ASC']),
            'transitaires'=>$this->em->getRepository(Transitaire::class)->findBy([],['libelle'=>'ASC']),
            'compagnies'=>$this->em->getRepository(CompagnieNavigation::class)->findBy([],['libelle'=>'ASC']),
            'formules'=>$disponibles,'statuts'=>DocumentsExport::STATUTS,
        ]);
    }

    #[Route('/logistique/documents/attendus', name: 'logistique_documents_attendus', methods: ['GET'])]
    public function attendus(Request $request): JsonResponse
    {
        $this->autoriser();
        $produit=$this->trouver(Produit::class,$request->query->get('produit'));
        $client=$this->trouver(Client::class,$request->query->get('client'));
        $liste=$this->documents->liste($produit,$client);
        $dossierId=(string) $request->query->get('dossier','');
        if ($dossierId !== '') {
            $dossier=$this->trouver(Dossier::class,$dossierId);
            if ((string) $dossier->getProduit()?->getId() !== (string) $produit->getId() || (string) $dossier->getClient()?->getId() !== (string) $client->getId()) throw $this->createAccessDeniedException('Produit/client differents de ceux du dossier.');
            $pieces=$this->em->getRepository(DocsDossier::class)->findBy(['code_dossier'=>$dossier],['libelle'=>'ASC']);
            if ($pieces) {
                $documents=[];
                foreach ($pieces as $doc) {
                    $fichier=$doc->getFilename();
                    $documents[]=['id'=>(string) $doc->getId(),'libelle'=>$doc->getLibelle(),
                        'statut'=>DocumentsExport::STATUTS[$doc->getStatut()] ?? $doc->getStatut(),
                        'fichier'=>$fichier ? ($doc->getNomOriginal() ?: basename($fichier)) : null,
                        'visualiser'=>$fichier ? $this->generateUrl('logistique_fichier_document',['id'=>$doc->getId(),'visualiser'=>1]) : null,
                        'telecharger'=>$fichier ? $this->generateUrl('logistique_fichier_document',['id'=>$doc->getId()]) : null];
                }
                return $this->json(['documents'=>$documents,'personnalise'=>$liste['personnalise'],'dossier'=>true,'suivi'=>$this->generateUrl('logistique_suivi_documents',['id'=>$dossier->getId()])]);
            }
        }
        return $this->json(['documents'=>$liste['documents'],'personnalise'=>$liste['personnalise'],'dossier'=>false]);
    }

    #[Route('/logistique/modeles-documentaires', name: 'logistique_modeles_documents', methods: ['GET','POST'])]
    public function modeles(Request $request): Response
    {
        $this->autoriser();
        if ($request->isMethod('POST')) {
            $this->jeton($request);
            try {
                $produit=$this->trouver(Produit::class,$request->request->get('produit'));
                $client=$request->request->get('client') ? $this->trouver(Client::class,$request->request->get('client')) : null;
                $ids=array_unique($request->request->all('documents'));
                if ($ids===[]) throw new \InvalidArgumentException('Selectionnez au moins un document.');
                $types=[]; foreach ($ids as $id) $types[]=$this->trouver(DocType::class,$id);
                $this->em->wrapInTransaction(function () use ($produit,$client,$types): void {
                    $modele=$this->em->getRepository(ModeleDoc::class)->findOneBy(['code_produit'=>$produit,'code_client'=>$client]);
                    $modele ??= new ModeleDoc();
                    foreach ($modele->getDetailsModeleDocs()->toArray() as $detail) { $modele->removeDetailsModeleDoc($detail); $this->em->remove($detail); }
                    $modele->setCodeProduit($produit)->setCodeClient($client)->setStandard($client===null)->setNomModele(mb_substr($produit->getLibelle().' / '.($client?->getNomClient() ?? 'Standard'),0,100));
                    $this->em->persist($modele);
                    foreach ($types as $type) { $detail=(new DetailsModeleDoc())->setCodeDoc($type); $modele->addDetailsModeleDoc($detail); $this->em->persist($detail); }
                    $this->em->flush();
                });
                if ($request->isXmlHttpRequest()) return $this->json(['message'=>'Modèle enregistré.']);
                $this->addFlash('success','Modele enregistre. Les dossiers existants conservent leur liste et leurs fichiers.');
            } catch (\Throwable $e) { if ($request->isXmlHttpRequest()) return $this->json(['message'=>$e->getMessage()],422); $this->addFlash('danger',$e->getMessage()); }
            return $this->redirectToRoute('logistique_modeles_documents');
        }
        return $this->render('logistique/gestion/modeles.html.twig',[
            'produits'=>$this->em->getRepository(Produit::class)->findBy([],['libelle'=>'ASC']),
            'clients'=>$this->em->getRepository(Client::class)->findBy([],['nom_client'=>'ASC']),
            'types'=>$this->em->getRepository(DocType::class)->findBy([],['libelle'=>'ASC']),
            'modeles'=>$this->em->getRepository(ModeleDoc::class)->findBy([],['nom_modele'=>'ASC']),
        ]);
    }

    #[Route('/logistique/embarquements/{id}/suivi-documentaire', name: 'logistique_suivi_documents', methods: ['GET','POST'])]
    public function suivi(Request $request, string $id): Response
    {
        $this->autoriser(); $dossier=$this->trouver(Dossier::class,$id);
        if ($request->isMethod('POST')) {
            $this->jeton($request); $nouveau=null; $ancien=null;
            try {
                $this->em->wrapInTransaction(function () use ($request,$id,$dossier,&$nouveau,&$ancien): void {
                    $this->em->refresh($dossier, LockMode::PESSIMISTIC_WRITE);
                    if ($dossier->isCloture()) throw new \InvalidArgumentException('Rouvrez le dossier avant de modifier son suivi.');
                    $action=(string) $request->request->get('action');
                    if ($action==='initialiser') { $this->documents->initialiser($dossier); return; }
                    if ($action==='ajouter') { $this->documents->ajouter($dossier,(string) $request->request->get('libelle')); return; }
                    $doc=$this->trouver(DocsDossier::class,$request->request->get('document'));
                    $this->em->refresh($doc, LockMode::PESSIMISTIC_WRITE);
                    if ((string) $doc->getCodeDossier()?->getId()!==$id) throw $this->createAccessDeniedException('Document et dossier differents.');
                    if ($action==='upload') {
                        $f=$request->files->get('fichier');
                        if (!$f instanceof UploadedFile || $f->getError()===UPLOAD_ERR_NO_FILE) throw new \InvalidArgumentException('Selectionnez un fichier pour ce document.');
                        $nouveau=$this->stockage->enregistrer($f); $ancien=$doc->getFilename();
                        $doc->setFilename($nouveau['fichier'])->setNomOriginal($nouveau['nom'])->setStatut('POUR_APPROBATION')->setTransmisLe(null)->setTransmisPar(null);
                    } elseif ($action==='statut') {
                        $s=(string) $request->request->get('statut');
                        if (!isset(DocumentsExport::STATUTS[$s])) throw new \InvalidArgumentException('Statut invalide.');
                        if ($s==='APPROUVE' && !$doc->getFilename()) throw new \InvalidArgumentException('Chargez le document avant de l’approuver.');
                        if ($doc->getTransmisLe()) throw new \InvalidArgumentException('Un document deja transmis ne peut pas etre requalifie sans charger une nouvelle version.');
                        $doc->setStatut($s);
                    } elseif ($action==='transmettre') {
                        if (!$doc->getFilename() || $doc->getStatut()!=='APPROUVE') throw new \InvalidArgumentException('Le document doit etre charge et approuve avant la transmission.');
                        $doc->setTransmisLe(new \DateTimeImmutable())->setTransmisPar(mb_substr((string) $this->getUser()->getUserIdentifier(),0,180));
                    } else throw new \InvalidArgumentException('Action inconnue.');
                });
                if ($nouveau && $ancien && $this->stockage->chemin($ancien)) $this->stockage->supprimer($ancien);
                if ($request->isXmlHttpRequest()) return $this->json(['message'=>'Suivi documentaire mis à jour.']);
                $this->addFlash('success','Suivi documentaire mis a jour.');
            } catch (\Throwable $e) { if ($nouveau) $this->stockage->supprimer($nouveau['fichier']); if ($request->isXmlHttpRequest()) return $this->json(['message'=>$e->getMessage()],422); $this->addFlash('danger',$e->getMessage()); }
            return $this->redirectToRoute('logistique_suivi_documents',['id'=>$id]);
        }
        return $this->render('logistique/gestion/suivi.html.twig',[
            'dossier'=>$dossier, 'documents'=>$this->em->getRepository(DocsDossier::class)->findBy(['code_dossier'=>$dossier],['libelle'=>'ASC']),
            'suivi'=>$this->em->getRepository(SuiviEmbarquement::class)->findOneBy(['dossier'=>$dossier]), 'statuts'=>DocumentsExport::STATUTS,
        ]);
    }

    #[Route('/logistique/fichier/document/{id}', name: 'logistique_fichier_document', methods: ['GET'])]
    public function document(string $id, Request $request): BinaryFileResponse
    {
        $this->autoriser(); $doc=$this->trouver(DocsDossier::class,$id);
        $p=$this->stockage->chemin($doc->getFilename());
        if (!$p && $doc->getFilename() && basename($doc->getFilename())===$doc->getFilename()) {
            $racine=realpath((string) $this->getParameter('dossier_docs_directory'));
            $candidat=$racine ? realpath($racine.'/'.$doc->getFilename()) : false;
            if ($candidat && str_starts_with($candidat,$racine.DIRECTORY_SEPARATOR) && is_file($candidat)) $p=$candidat;
        }
        if (!$p) throw $this->createNotFoundException('Fichier indisponible.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($p) ?: 'application/octet-stream';
        $inline=$request->query->getBoolean('visualiser') && in_array($mime,['application/pdf','image/jpeg','image/png','image/webp'],true);
        $reponse=$this->file($p,$doc->getNomOriginal() ?? basename($p),$inline ? 'inline' : 'attachment');
        $reponse->headers->set('Content-Type',$mime);
        $reponse->headers->set('X-Content-Type-Options','nosniff');
        $reponse->headers->set('Cache-Control','private, no-store');
        if ($inline) $reponse->headers->set('Content-Security-Policy',"default-src 'none'; frame-ancestors 'self'");
        return $reponse;
    }

    #[Route('/logistique/fichier/formule/{id}', name: 'logistique_fichier_formule', methods: ['GET'])]
    public function fichierFormule(string $id): BinaryFileResponse
    {
        $this->autoriser(); $f=$this->trouver(FormuleExport::class,$id); $p=$this->stockage->chemin($f->getFichier());
        if (!$p) throw $this->createNotFoundException('Fichier indisponible.');
        return $this->file($p,$f->getNomOriginal() ?? basename($p));
    }

    #[Route('/logistique/fichier/rapport/{id}', name: 'logistique_fichier_rapport', methods: ['GET'])]
    public function rapport(string $id): BinaryFileResponse
    {
        $this->autoriser(); $d=$this->trouver(Dossier::class,$id); $s=$this->em->getRepository(SuiviEmbarquement::class)->findOneBy(['dossier'=>$d]); $p=$this->stockage->chemin($s?->getFichierRapport());
        if (!$p) throw $this->createNotFoundException('Rapport indisponible.');
        return $this->file($p,$s->getNomRapport() ?? basename($p));
    }
}
