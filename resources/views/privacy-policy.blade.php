<!DOCTYPE html>

<html lang="fr" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Politique de confidentialité — CCPA</title>

<meta name="description"
      content="Politique de confidentialité du Collège Catholique Père Aupiais et de sa plateforme numérique.">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
      rel="stylesheet">

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<script src="https://cdn.tailwindcss.com"></script>

<style>
    * {
        font-family: 'Inter', system-ui, sans-serif;
    }

    body {
        color: #0f172a;
        background: #f8fafc;
        line-height: 1.7;
    }

    .gradient {
        background: linear-gradient(
            135deg,
            #00AEEF 0%,
            #4D4DFF 50%,
            #AE00FF 100%
        );
    }

    .gradient-text {
        background: linear-gradient(
            135deg,
            #00AEEF 0%,
            #4D4DFF 50%,
            #AE00FF 100%
        );

        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    header {
        background: rgba(255, 255, 255, 0.92);
        backdrop-filter: blur(12px);
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
    }

    .logo-wrapper {
        display: inline-flex;
        align-items: center;
        gap: .75rem;
        text-decoration: none;
    }

    .logo-img {
        width: 42px;
        height: 42px;
        border-radius: 11px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .logo-img img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .logo-text {
        font-weight: 900;
        font-size: 17px;
    }

    .policy-card {
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 18px;
        box-shadow: 0 10px 35px rgba(15, 23, 42, .06);
    }

    .policy-section {
        scroll-margin-top: 100px;
    }

    .policy-section h2 {
        font-size: 1.35rem;
        font-weight: 800;
        margin-bottom: .75rem;
        color: #0f172a;
    }

    .policy-section h3 {
        font-size: 1.05rem;
        font-weight: 700;
        margin-top: 1.25rem;
        margin-bottom: .5rem;
        color: #1e293b;
    }

    .policy-section p {
        color: #475569;
        margin-bottom: .9rem;
    }

    .policy-section ul {
        list-style: disc;
        padding-left: 1.4rem;
        color: #475569;
        margin-bottom: 1rem;
    }

    .policy-section li {
        margin-bottom: .45rem;
    }

    .info-box {
        border-left: 4px solid #4D4DFF;
        background: #f8faff;
    }

    footer {
        background: linear-gradient(
            180deg,
            #0b1220 0%,
            #0a0f1a 60%,
            #0b1220 100%
        );
    }
</style>

</head>

<body>

{{-- ================= HEADER ================= --}}

<header class="fixed top-0 left-0 right-0 z-50">

<div class="max-w-6xl mx-auto px-4">

    <div class="h-16 flex items-center justify-between">

        <a href="{{ url('/') }}" class="logo-wrapper">

            <div class="logo-img">
                <img
                    src="{{ asset('assets/images/LOGOCCPA.jpeg') }}"
                    alt="Collège Catholique Père Aupiais"
                >
            </div>

            <span class="logo-text">
                CCPA
            </span>

        </a>

        <a href="{{ url('/') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-indigo-600 transition">

            <i class="fas fa-arrow-left"></i>

            Retour à l'accueil

        </a>

    </div>

</div>

</header>

{{-- ================= CONTENU ================= --}}

<main class="pt-28 pb-16">

<div class="max-w-5xl mx-auto px-4">

    {{-- TITRE --}}

    <div class="text-center mb-10">

        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-blue-50 mb-4">

            <i class="fas fa-shield-halved text-blue-600"></i>

            <span class="text-sm font-bold text-indigo-600">
                Protection des données
            </span>

        </div>

        <h1 class="text-3xl md:text-5xl font-black mb-4">

            Politique de

            <span class="gradient-text">
                confidentialité
            </span>

        </h1>

        <p class="text-slate-500 max-w-2xl mx-auto">

            Le Collège Catholique Père Aupiais accorde une importance
            particulière à la protection des données personnelles de
            ses élèves, parents, responsables légaux et utilisateurs
            de ses services numériques.

        </p>

        <p class="text-sm text-slate-400 mt-4">

            Dernière mise à jour :
            {{ now()->format('d/m/Y') }}

        </p>

    </div>


    <div class="policy-card p-6 md:p-10">


        {{-- 1. INTRODUCTION --}}

        <section class="policy-section mb-10">

            <h2>
                1. Introduction
            </h2>

            <p>
                Le Collège Catholique Père Aupiais (CCPA) accorde une
                importance particulière à la protection des données
                personnelles et à la confidentialité des informations
                qui lui sont confiées dans le cadre de ses activités
                scolaires et administratives.
            </p>

            <p>
                Le CCPA dispose d'une plateforme numérique accessible
                notamment à travers une application mobile destinée
                aux parents et responsables légaux des élèves.
            </p>

            <p>
                Cette plateforme permet notamment aux parents de suivre
                la scolarité de leurs enfants, de consulter les notes,
                de recevoir les communiqués de l'établissement,
                d'échanger avec l'administration et d'effectuer
                certains paiements liés à la scolarité et à la cantine.
            </p>

            <p>
                La présente Politique de confidentialité explique
                comment les données personnelles sont collectées,
                utilisées, conservées et protégées dans le cadre
                de ces services.
            </p>

        </section>


        {{-- 2. RESPONSABLE --}}

        <section class="policy-section mb-10">

            <h2>
                2. Responsable du traitement
            </h2>

            <p>
                Le responsable du traitement des données personnelles
                collectées dans le cadre de la plateforme est :
            </p>

            <div class="info-box rounded-xl p-5 my-5">

                <p class="mb-1">
                    <strong>Nom :</strong>
                    Collège Catholique Père Aupiais (CCPA)
                </p>

                <p class="mb-1">
                    <strong>Adresse :</strong>
                    [ADRESSE DU CCPA]
                </p>

                <p class="mb-1">
                    <strong>Téléphone :</strong>
                    [NUMÉRO DE TÉLÉPHONE]
                </p>

                <p class="mb-1">
                    <strong>Email :</strong>
                    [EMAIL OFFICIEL]
                </p>

                <p class="mb-0">
                    <strong>Site web :</strong>
                    [SITE WEB]
                </p>

            </div>

        </section>


        {{-- 3. UTILISATEURS --}}

        <section class="policy-section mb-10">

            <h2>
                3. Utilisateurs concernés
            </h2>

            <p>
                La plateforme numérique du CCPA peut être utilisée
                par différentes catégories d'utilisateurs, notamment :
            </p>

            <ul>

                <li>
                    les parents et responsables légaux des élèves ;
                </li>

                <li>
                    les élèves, lorsque certaines fonctionnalités
                    leur sont accessibles ;
                </li>

                <li>
                    les membres de l'administration ;
                </li>

                <li>
                    les enseignants et personnels autorisés ;
                </li>

                <li>
                    tout autre utilisateur habilité par le CCPA.
                </li>

            </ul>

            <p>
                Les informations accessibles à chaque utilisateur
                dépendent de son profil et des autorisations qui lui
                sont attribuées.
            </p>

        </section>


        {{-- 4. DONNEES COLLECTEES --}}

        <section class="policy-section mb-10">

            <h2>
                4. Données personnelles collectées
            </h2>

            <p>
                Dans le cadre de l'utilisation de la plateforme,
                différentes catégories de données peuvent être
                collectées et traitées.
            </p>


            <h3>
                4.1. Données relatives aux parents et responsables légaux
            </h3>

            <ul>

                <li>Nom et prénoms ;</li>

                <li>Numéro de téléphone ;</li>

                <li>Adresse e-mail ;</li>

                <li>Informations relatives au compte utilisateur ;</li>

                <li>Identifiants de connexion ;</li>

                <li>Informations nécessaires à la sécurité du compte ;</li>

                <li>Informations relatives aux échanges avec l'administration.</li>

            </ul>


            <h3>
                4.2. Données relatives aux élèves
            </h3>

            <ul>

                <li>Nom et prénoms ;</li>

                <li>Date de naissance ;</li>

                <li>Classe ;</li>

                <li>Matricule ou identifiant scolaire ;</li>

                <li>Notes et résultats scolaires ;</li>

                <li>Moyennes et appréciations ;</li>

                <li>Informations relatives à la scolarité ;</li>

                <li>Informations relatives à la cantine ;</li>

                <li>Informations administratives nécessaires au suivi de l'élève.</li>

            </ul>


            <h3>
                4.3. Données relatives aux paiements
            </h3>

            <ul>

                <li>Montant du paiement ;</li>

                <li>Date et heure de l'opération ;</li>

                <li>Nature du paiement ;</li>

                <li>Élève concerné ;</li>

                <li>Référence de transaction ;</li>

                <li>Statut du paiement ;</li>

                <li>Informations relatives au moyen de paiement utilisé.</li>

            </ul>


            <h3>
                4.4. Données relatives aux communications
            </h3>

            <ul>

                <li>Messages envoyés et reçus ;</li>

                <li>Date et heure des échanges ;</li>

                <li>Destinataires ;</li>

                <li>Pièces jointes éventuellement transmises ;</li>

                <li>Informations nécessaires au suivi des demandes.</li>

            </ul>


            <h3>
                4.5. Données techniques
            </h3>

            <ul>

                <li>Adresse IP ;</li>

                <li>Type d'appareil ;</li>

                <li>Système d'exploitation ;</li>

                <li>Version de l'application ;</li>

                <li>Données de connexion ;</li>

                <li>Journaux techniques et de sécurité.</li>

            </ul>

        </section>


        {{-- 5. FINALITES --}}

        <section class="policy-section mb-10">

            <h2>
                5. Finalités du traitement
            </h2>

            <p>
                Les données personnelles sont traitées notamment
                afin de permettre au CCPA d'assurer ses missions
                scolaires, administratives et de communication.
            </p>

            <ul>

                <li>
                    Créer et gérer les comptes utilisateurs ;
                </li>

                <li>
                    Permettre aux parents de suivre la scolarité
                    de leurs enfants ;
                </li>

                <li>
                    Communiquer les notes et résultats scolaires ;
                </li>

                <li>
                    Permettre la consultation des informations
                    relatives aux élèves ;
                </li>

                <li>
                    Transmettre les communiqués et informations
                    officielles de l'établissement ;
                </li>

                <li>
                    Permettre les échanges entre les parents et
                    l'administration ;
                </li>

                <li>
                    Assurer le suivi des demandes adressées
                    à l'établissement ;
                </li>

                <li>
                    Gérer les frais de scolarité ;
                </li>

                <li>
                    Permettre le paiement des frais de scolarité ;
                </li>

                <li>
                    Gérer les frais de cantine ;
                </li>

                <li>
                    Permettre le paiement des frais de cantine ;
                </li>

                <li>
                    Assurer le suivi administratif des élèves ;
                </li>

                <li>
                    Améliorer les services numériques du CCPA ;
                </li>

                <li>
                    Assurer la sécurité de la plateforme ;
                </li>

                <li>
                    Prévenir les accès non autorisés et les activités
                    frauduleuses ;
                </li>

                <li>
                    Respecter les obligations légales et réglementaires
                    applicables.
                </li>

            </ul>

        </section>


        {{-- 6. DONNEES SCOLAIRES --}}

        <section class="policy-section mb-10">

            <h2>
                6. Données scolaires et suivi des élèves
            </h2>

            <p>
                Les informations relatives à la scolarité des élèves
                constituent une partie importante des données traitées
                par la plateforme.
            </p>

            <p>
                Selon les droits qui lui sont attribués, un parent
                ou responsable légal peut notamment consulter :
            </p>

            <ul>

                <li>les notes ;</li>

                <li>les moyennes ;</li>

                <li>les résultats scolaires ;</li>

                <li>les appréciations ;</li>

                <li>les informations concernant la classe ;</li>

                <li>certaines informations administratives ;</li>

                <li>les informations relatives à la cantine.</li>

            </ul>

            <p>
                L'accès à ces informations est limité aux utilisateurs
                autorisés. Un parent ou responsable légal ne peut
                accéder qu'aux informations des élèves qui lui sont
                associés dans le système.
            </p>

        </section>


        {{-- 7. COMMUNICATION --}}

        <section class="policy-section mb-10">

            <h2>
                7. Communications avec l'administration
            </h2>

            <p>
                La plateforme peut permettre aux parents et responsables
                légaux de communiquer avec certains membres de
                l'administration du CCPA.
            </p>

            <p>
                Les échanges peuvent être conservés afin de permettre
                notamment :
            </p>

            <ul>

                <li>le suivi des demandes ;</li>

                <li>la continuité des échanges ;</li>

                <li>le traitement des sollicitations ;</li>

                <li>la gestion administrative ;</li>

                <li>la sécurité et la traçabilité des communications.</li>

            </ul>

        </section>


        {{-- 8. COMMUNIQUES --}}

        <section class="policy-section mb-10">

            <h2>
                8. Communiqués et notifications
            </h2>

            <p>
                Le CCPA peut utiliser la plateforme afin de transmettre
                aux parents et responsables légaux des informations
                relatives à la vie de l'établissement.
            </p>

            <ul>

                <li>Annonces administratives ;</li>

                <li>Informations scolaires ;</li>

                <li>Événements et activités ;</li>

                <li>Réunions ;</li>

                <li>Examens et évaluations ;</li>

                <li>Informations relatives à la cantine ;</li>

                <li>Rappels concernant les paiements ;</li>

                <li>Communications urgentes.</li>

            </ul>

        </section>


        {{-- 9. PAIEMENTS --}}

        <section class="policy-section mb-10">

            <h2>
                9. Paiement de la scolarité et de la cantine
            </h2>

            <p>
                La plateforme peut permettre aux parents ou responsables
                légaux d'effectuer certains paiements liés aux services
                de l'établissement.
            </p>

            <p>
                Ces paiements peuvent notamment concerner :
            </p>

            <ul>

                <li>les frais de scolarité ;</li>

                <li>les frais de cantine ;</li>

                <li>certains services proposés par l'établissement.</li>

            </ul>

            <p>
                Les informations relatives aux transactions peuvent
                être enregistrées afin d'assurer leur suivi, leur
                rapprochement comptable, la consultation de l'historique
                des paiements et le traitement des éventuelles
                réclamations.
            </p>

            <p>
                Lorsque le paiement est effectué par l'intermédiaire
                d'un prestataire externe, certaines données peuvent
                être transmises à ce prestataire dans la mesure
                nécessaire à l'exécution de la transaction.
            </p>

        </section>


        {{-- 10. PARTAGE --}}

        <section class="policy-section mb-10">

            <h2>
                10. Partage et destinataires des données
            </h2>

            <p>
                Le CCPA ne vend pas les données personnelles de ses
                élèves, parents ou responsables légaux.
            </p>

            <p>
                Certaines données peuvent toutefois être accessibles
                aux personnes ou organisations qui en ont besoin pour
                assurer les services proposés par la plateforme.
            </p>

            <ul>

                <li>
                    Membres habilités de l'administration ;
                </li>

                <li>
                    Enseignants ou personnels autorisés ;
                </li>

                <li>
                    Prestataires techniques chargés de l'hébergement,
                    de la maintenance ou de la sécurité ;
                </li>

                <li>
                    Prestataires de paiement ;
                </li>

                <li>
                    Prestataires chargés de l'envoi de notifications ;
                </li>

                <li>
                    Autorités compétentes lorsque la loi l'exige.
                </li>

            </ul>

            <p>
                Les accès doivent être limités aux informations
                nécessaires à l'accomplissement des missions concernées.
            </p>

        </section>


        {{-- 11. SECURITE --}}

        <section class="policy-section mb-10">

            <h2>
                11. Sécurité des données
            </h2>

            <p>
                Le CCPA met en œuvre des mesures techniques et
                organisationnelles destinées à protéger les données
                personnelles contre les accès non autorisés, la perte,
                la destruction, la modification, la divulgation ou
                l'utilisation abusive.
            </p>

            <p>
                Ces mesures peuvent notamment comprendre :
            </p>

            <ul>

                <li>Authentification des utilisateurs ;</li>

                <li>Gestion des droits et niveaux d'accès ;</li>

                <li>Protection des communications ;</li>

                <li>Sécurisation des comptes ;</li>

                <li>Journalisation des opérations ;</li>

                <li>Sauvegarde des données ;</li>

                <li>Maintenance et surveillance des systèmes.</li>

            </ul>

            <p>
                Malgré les mesures mises en œuvre, aucun système
                informatique ne peut garantir une sécurité absolue.
            </p>

            <p>
                Les utilisateurs sont également responsables de la
                confidentialité de leurs identifiants et doivent
                signaler rapidement toute utilisation suspecte
                de leur compte.
            </p>

        </section>


        {{-- 12. CONSERVATION --}}

        <section class="policy-section mb-10">

            <h2>
                12. Conservation des données
            </h2>

            <p>
                Les données personnelles sont conservées pendant une
                durée nécessaire aux finalités pour lesquelles elles
                sont traitées et conformément aux obligations légales,
                administratives, comptables et scolaires applicables.
            </p>

            <p>
                Certaines données relatives à la scolarité, aux
                paiements, aux communications et à la gestion
                administrative peuvent être conservées pendant une
                période plus longue lorsque cela est nécessaire aux
                obligations de l'établissement.
            </p>

            <p>
                À l'expiration des périodes de conservation applicables,
                les données sont supprimées, anonymisées ou archivées
                conformément aux règles applicables.
            </p>

        </section>


        {{-- 13. DROITS --}}

        <section class="policy-section mb-10">

            <h2>
                13. Droits des personnes
            </h2>

            <p>
                Dans les conditions prévues par la réglementation
                applicable, les personnes concernées peuvent disposer
                de différents droits concernant leurs données personnelles,
                notamment :
            </p>

            <ul>

                <li>
                    Droit d'accès aux données personnelles ;
                </li>

                <li>
                    Droit de rectification des données inexactes ;
                </li>

                <li>
                    Droit de demander la suppression de certaines données ;
                </li>

                <li>
                    Droit de s'opposer à certains traitements ;
                </li>

                <li>
                    Droit de demander la limitation de certains traitements,
                    lorsque celui-ci est applicable ;
                </li>

                <li>
                    Droit d'obtenir des informations sur l'utilisation
                    des données.
                </li>

            </ul>

            <p>
                L'exercice de certains droits peut être limité lorsque
                la conservation ou le traitement des données est
                nécessaire au respect d'une obligation légale ou à la
                gestion administrative et scolaire de l'établissement.
            </p>

            <p>
                Pour exercer leurs droits, les utilisateurs peuvent
                contacter le CCPA aux coordonnées indiquées dans
                la présente politique.
            </p>

            <p>
                Le CCPA peut demander certaines informations afin de
                vérifier l'identité du demandeur avant de traiter
                une demande portant sur des données personnelles.
            </p>

        </section>


        {{-- 14. MINEURS --}}

        <section class="policy-section mb-10">

            <h2>
                14. Données concernant les élèves mineurs
            </h2>

            <p>
                La plateforme traite des données relatives à des élèves
                pouvant être mineurs. Le CCPA accorde une attention
                particulière à la protection de ces informations.
            </p>

            <p>
                L'accès aux données relatives à un élève est limité
                aux personnes dûment autorisées, notamment aux parents
                ou responsables légaux associés à cet élève dans
                les systèmes de l'établissement.
            </p>

            <p>
                Les utilisateurs disposant d'un accès aux informations
                d'un élève doivent veiller à ne pas communiquer ces
                informations à des personnes non autorisées.
            </p>

        </section>


        {{-- 15. APPLICATION MOBILE --}}

        <section class="policy-section mb-10">

            <h2>
                15. Utilisation de l'application mobile
            </h2>

            <p>
                L'application mobile du CCPA peut nécessiter certaines
                autorisations techniques afin de permettre son bon
                fonctionnement.
            </p>

            <p>
                Selon les fonctionnalités utilisées, l'application
                peut notamment utiliser certaines fonctionnalités
                de l'appareil pour :
            </p>

            <ul>

                <li>recevoir des notifications ;</li>

                <li>maintenir la connexion au compte ;</li>

                <li>afficher les informations de la plateforme ;</li>

                <li>permettre l'utilisation des fonctionnalités
                    proposées par l'application.</li>

            </ul>

            <p>
                Les autorisations demandées sont utilisées dans la
                mesure nécessaire au fonctionnement des fonctionnalités
                concernées.
            </p>

        </section>


        {{-- 16. COOKIES --}}

        <section class="policy-section mb-10">

            <h2>
                16. Cookies et technologies similaires
            </h2>

            <p>
                La plateforme web peut utiliser des cookies ou des
                technologies similaires nécessaires notamment au
                fonctionnement du service, à l'authentification,
                à la sécurité et à la mémorisation de certaines
                préférences.
            </p>

            <p>
                Les utilisateurs peuvent configurer leur navigateur
                afin de contrôler certains cookies.
            </p>

            <p>
                La désactivation de certains cookies nécessaires peut
                toutefois affecter le fonctionnement de certaines
                fonctionnalités de la plateforme.
            </p>

        </section>


        {{-- 17. PRESTATAIRES --}}

        <section class="policy-section mb-10">

            <h2>
                17. Services et prestataires externes
            </h2>

            <p>
                Certaines fonctionnalités de la plateforme peuvent
                reposer sur des services fournis par des prestataires
                externes, notamment pour :
            </p>

            <ul>

                <li>l'hébergement ;</li>

                <li>le stockage ;</li>

                <li>l'envoi de notifications ;</li>

                <li>le traitement des paiements ;</li>

                <li>la sécurité ;</li>

                <li>la maintenance technique.</li>

            </ul>

            <p>
                Ces prestataires peuvent traiter certaines données
                dans la mesure nécessaire à la fourniture de leurs
                services.
            </p>

        </section>


        {{-- 18. TRANSFERT --}}

        <section class="policy-section mb-10">

            <h2>
                18. Transfert des données
            </h2>

            <p>
                Dans le cadre de l'utilisation de certains services
                techniques, les données peuvent être hébergées ou
                traitées sur des infrastructures situées dans un
                autre pays.
            </p>

            <p>
                Lorsque cela est applicable, ces transferts sont
                effectués conformément aux exigences légales et
                réglementaires relatives à la protection des données
                personnelles.
            </p>

        </section>


        {{-- 19. RESPONSABILITE UTILISATEUR --}}

        <section class="policy-section mb-10">

            <h2>
                19. Responsabilité de l'utilisateur
            </h2>

            <p>
                L'utilisateur s'engage notamment à :
            </p>

            <ul>

                <li>
                    conserver ses identifiants de connexion confidentiels ;
                </li>

                <li>
                    ne pas partager son compte avec une personne non autorisée ;
                </li>

                <li>
                    utiliser uniquement son propre compte ;
                </li>

                <li>
                    signaler rapidement toute perte ou compromission
                    de ses identifiants ;
                </li>

                <li>
                    ne pas tenter d'accéder aux données d'autres utilisateurs ;
                </li>

                <li>
                    ne pas utiliser la plateforme à des fins frauduleuses
                    ou illicites.
                </li>

            </ul>

        </section>


        {{-- 20. MODIFICATION --}}

        <section class="policy-section mb-10">

            <h2>
                20. Modification de la Politique de confidentialité
            </h2>

            <p>
                Le CCPA peut modifier la présente Politique de
                confidentialité afin de tenir compte de l'évolution
                de ses services, de ses fonctionnalités numériques,
                de ses pratiques de traitement des données ou des
                exigences légales et réglementaires applicables.
            </p>

            <p>
                La date de dernière mise à jour est indiquée au début
                de la présente politique.
            </p>

            <p>
                En cas de modification importante, le CCPA peut
                informer les utilisateurs par les moyens de communication
                appropriés, notamment via la plateforme ou l'application
                mobile.
            </p>

        </section>


        {{-- 21. CONTACT --}}

        <section class="policy-section">

            <h2>
                21. Nous contacter
            </h2>

            <p>
                Pour toute question concernant la présente Politique
                de confidentialité, l'utilisation des données
                personnelles ou l'exercice des droits, vous pouvez
                contacter le Collège Catholique Père Aupiais :
            </p>

            <div class="info-box rounded-xl p-5">

                <p class="mb-1">
                    <strong>
                        Collège Catholique Père Aupiais (CCPA)
                    </strong>
                </p>

                <p class="mb-1">
                    <i class="fas fa-location-dot mr-2 text-indigo-600"></i>
                    [ADRESSE]
                </p>

                <p class="mb-1">
                    <i class="fas fa-phone mr-2 text-indigo-600"></i>
                    [TÉLÉPHONE]
                </p>

                <p class="mb-1">
                    <i class="fas fa-envelope mr-2 text-indigo-600"></i>
                    [EMAIL]
                </p>

                <p class="mb-0">
                    <i class="fas fa-globe mr-2 text-indigo-600"></i>
                    [SITE WEB]
                </p>

            </div>

        </section>

    </div>

</div>

</main>

{{-- ================= FOOTER ================= --}}

<footer class="text-slate-300">

<div class="max-w-6xl mx-auto px-4 py-10">

    <div class="flex flex-col md:flex-row items-center justify-between gap-4">

        <div class="flex items-center gap-3">

            <div class="w-9 h-9 rounded-lg gradient flex items-center justify-center">

                <i class="fas fa-school text-white"></i>

            </div>

            <span class="font-black text-white">
                CCPA
            </span>

        </div>


        <div class="flex flex-wrap justify-center gap-5 text-sm">

            <a href="{{ url('/') }}"
               class="hover:text-white transition">

                Accueil

            </a>

            <a href="{{ route('privacy.policy') }}"
               class="text-white font-semibold">

                Confidentialité

            </a>

        </div>

    </div>


    <div class="border-t border-slate-700 mt-8 pt-6 text-center text-xs text-slate-500">

        © {{ date('Y') }}
        Collège Catholique Père Aupiais.
        Tous droits réservés.

    </div>

</div>


</footer>

</body>
</html>
