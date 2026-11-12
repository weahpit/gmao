<?php

/*
 * Fuseau horaire de l'application.
 *
 * L'application travaille en GMT+0 (UTC) : toutes les dates generees par PHP
 * (date(), new \DateTime(), \DateTimeImmutable(), etc.) et toutes les dates
 * enregistrees en base sont exprimees en temps universel.
 *
 * Definir le fuseau ici garantit qu'il est applique TRES TOT, avant le demarrage
 * du noyau, quel que soit le reglage du serveur.
 */
date_default_timezone_set('UTC');
