<?php

namespace App\Controller;

use App\EventSubscriber\LocaleSubscriber;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Changement de langue de l'interface (FR / EN).
 *
 * La langue choisie est memorisee en session : elle est reutilisee sur
 * toutes les pages suivantes. Si aucune langue n'a ete choisie, la
 * LocaleSubscriber retient celle du navigateur (parametres regionaux).
 *
 * Securite : la valeur recue est passee par une liste blanche (fr, en)
 * et la redirection suit uniquement l'en-tete Referer de la meme page
 * (chemin interne), jamais une URL externe.
 */
final class LangueController extends AbstractController
{
    #[Route('/langue/{_locale}', name: 'app_langue_changer', requirements: ['_locale' => 'fr|en'], methods: ['GET'])]
    public function changer(string $_locale, Request $request): Response
    {
        if (!in_array($_locale, LocaleSubscriber::LANGUES, true)) {
            $_locale = LocaleSubscriber::LANGUE_DEFAUT;
        }

        if ($request->hasSession()) {
            $request->getSession()->set('_locale', $_locale);
        }

        // Retour sur la page d'origine (chemin interne uniquement)
        $retour = $request->headers->get('referer');
        $cible = null;

        if ($retour) {
            $parties = parse_url($retour);
            $hote = $parties['host'] ?? null;

            if ($hote === null || $hote === $request->getHost()) {
                $chemin = $parties['path'] ?? '/';
                // Une URL contenant "//" apres le chemin pourrait mener ailleurs
                if (!str_starts_with($chemin, '//') && !str_contains($chemin, '/langue/')) {
                    $cible = $chemin . (isset($parties['query']) ? '?' . $parties['query'] : '');
                }
            }
        }

        if ($cible === null) {
            $cible = $this->generateUrl('app_dashboard');
        }

        return $this->redirect($cible);
    }
}
