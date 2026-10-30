<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ajoute des en-tetes HTTP de securite a chaque reponse.
 * Protege contre le clickjacking, le MIME-sniffing et les fuites de referrer.
 * S'enregistre automatiquement (autoconfigure) sans config supplementaire.
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        // Ne traiter que la requete principale (pas les sous-requetes internes)
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;

        // Empeche l'affichage du site dans une <iframe> d'un autre domaine (clickjacking)
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        // Empeche le navigateur de « deviner » le type MIME (anti XSS via fichiers)
        $headers->set('X-Content-Type-Options', 'nosniff');
        // Limite les informations de provenance envoyees aux sites tiers
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Desactive des API navigateur sensibles par defaut
        $headers->set('Permissions-Policy', 'geolocation=(self), camera=(), microphone=()');

        // Force HTTPS pour les prochaines visites (uniquement si la requete est deja en HTTPS,
        // pour ne pas bloquer un acces local en HTTP pendant le developpement).
        if ($event->getRequest()->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }
}
