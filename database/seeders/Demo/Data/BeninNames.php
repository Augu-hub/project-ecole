<?php

namespace Database\Seeders\Demo\Data;

/**
 * Identités béninoises réalistes pour le jeu de démonstration : noms de famille d'origines fon,
 * goun, yoruba/nago, mina, adja, bariba, dendi et afro-brésiliennes, prénoms chrétiens/français
 * souvent associés à un prénom local, quartiers de Cotonou / Abomey-Calavi, professions et
 * préfixes téléphoniques (numérotation à 10 chiffres « 01 XX XX XX XX » en vigueur depuis 2024).
 */
final class BeninNames
{
    /** Noms de famille (en majuscules, comme sur les registres d'état civil). */
    public const NOMS = [
        // Fon / Goun (Abomey, Cotonou, Porto-Novo, Ouidah)
        'HOUNTONDJI', 'AGBOSSOU', 'DOSSOU', 'KPADONOU', 'ADJOVI', 'HOUNKPATIN', 'ZINSOU', 'GBAGUIDI',
        'AKPLOGAN', 'SOGLO', 'TOSSOU', 'AHOUANDJINOU', 'AHOMADEGBE', 'ADANDE', 'AGOSSOU', 'AKAKPO',
        'AMOUSSOU', 'ASSOGBA', 'AZONHIHO', 'BOKO', 'CODJIA', 'DANSOU', 'DEGBEY', 'DJOSSOU',
        'GANDONOU', 'GNONLONFOUN', 'HOUESSOU', 'HOUNGBEDJI', 'HOUNSOU', 'KIKI', 'KOUDOGBO', 'KOUTON',
        'MEDEGAN', 'NOUDOFININ', 'SOSSA', 'TCHOBO', 'VODOUNON', 'ZANNOU', 'ZOSSOU', 'ADJAHO',
        'AGBANGLA', 'AHOKPE', 'ALLADAYE', 'APLOGAN', 'ASSOGBADJO', 'AZON', 'BADA', 'CAPO-CHICHI',
        'DAGBA', 'DEDJI', 'DJOGBENOU', 'DOGNON', 'DOSSA', 'FAGNON', 'FANOU', 'GBENOU',
        'GLELE', 'HOUEDANOU', 'HOUNNOU', 'KOUKPAKI', 'KPOSSOU', 'LOKOSSOU', 'MONTCHO', 'NOUATIN',
        'QUENUM', 'SAGBO', 'SEHOUETO', 'SODJI', 'TOGBE', 'YEHOUENOU', 'ZODEOUGAN', 'ZOUNON',
        'AKOHA', 'GBETO', 'HOUNSA', 'KOUMASSEGBO', 'AGBOTON', 'AHANHANZO', 'AKPOVI', 'ALOKPO',
        'DOVOEDO', 'GBEDJI', 'HOUNDJO', 'KINDJI', 'SOHOUNHLOUE', 'TOHOUEGNON', 'VIGNON', 'ZOHOUN',
        'AVOCE', 'AFFOGNON', 'ADJAKPA', 'HOUNMENOU', 'HONVOU', 'GOGAN', 'KAKPO', 'AHISSOU',
        // Yoruba / Nago (Porto-Novo, Pobè, Kétou, Savè)
        'ADEKAMBI', 'OLOUDE', 'ADEOTI', 'ADEYEMI', 'AFOLABI', 'AKANNI', 'ALAPINI', 'ATCHADE',
        'BABATOUNDE', 'BELLO', 'FADIPE', 'OKE', 'OKOUNDE', 'OSSENI', 'SALAMI', 'TIDJANI',
        'YESSOUFOU', 'ADJIBADE', 'OLADOKOUN', 'ODJO', 'AKINOCHO', 'ADEDJOUMA', 'OGOUNCHI', 'ADISSA',
        // Mina / Adja (Grand-Popo, Lokossa, Aplahoué)
        'AMEGAN', 'AKUE', 'LAWSON', 'AMOUZOU', 'AKOGBETO', 'HOUNGUE', 'DANHOUNDO', 'AGBODJAN',
        'AYIVI', 'KOFFI', 'MENSAH', 'ATTIOGBE', 'AMEDOME',
        // Bariba / Dendi (Parakou, Nikki, Kandi, Djougou)
        'BIO', 'SANNI', 'GOUNOU', 'SABI', 'OROU', 'BONI', 'GADO', 'DAFIA', 'KORA', 'YAROU', 'WOROU',
        'DAMBA', 'SIDI', 'CHABI', 'BIAOU', 'MAMA', 'ISSIFOU', 'IDRISSOU', 'MOUSSA', 'TOURE', 'MAIGA',
        'SOUMANOU', 'ABOUBAKAR',
        // Afro-brésiliens (Ouidah, Porto-Novo, Agoué)
        'DE SOUZA', "D'ALMEIDA", 'DA SILVA', 'DOS SANTOS', 'PARAISO', 'MEDEIROS', 'GOMEZ', "D'OLIVEIRA",
        'DA MATHA', 'DA COSTA',
    ];

    /** Prénoms masculins d'usage (chrétiens / français). */
    public const PRENOMS_M = [
        'Jean', 'Jean-Baptiste', 'Jean-Marie', 'Jean-Paul', 'Jean-Pierre', 'Jean-Claude', 'Jean-Luc',
        'Pierre', 'Paul', 'Joseph', 'Jacques', 'Michel', 'François', 'Emmanuel', 'Raphaël', 'Gabriel',
        'Augustin', 'Ambroise', 'Anselme', 'Arnaud', 'Aristide', 'Barnabé', 'Basile', 'Benoît', 'Bertin',
        'Boris', 'Brice', 'Cédric', 'Célestin', 'Charles', 'Christian', 'Clément', 'Constant', 'Cyrille',
        'Damien', 'Daniel', 'David', 'Denis', 'Désiré', 'Didier', 'Dieudonné', 'Dominique', 'Edgar',
        'Edmond', 'Éric', 'Ernest', 'Étienne', 'Eugène', 'Fabrice', 'Félix', 'Firmin', 'Florent',
        'Francis', 'Frédéric', 'Gaston', 'Gautier', 'Georges', 'Gérard', 'Germain', 'Gilles', 'Grégoire',
        'Guy', 'Hervé', 'Hilaire', 'Honoré', 'Ignace', 'Innocent', 'Isidore', 'Jérôme', 'Joël', 'Jonas',
        'Judicaël', 'Julien', 'Justin', 'Landry', 'Laurent', 'Léon', 'Léonard', 'Lionel', 'Luc', 'Marc',
        'Marcel', 'Martin', 'Mathieu', 'Maurice', 'Maxime', 'Médard', 'Narcisse', 'Nestor', 'Nicolas',
        'Noël', 'Octave', 'Olivier', 'Pacôme', 'Parfait', 'Pascal', 'Patrice', 'Prosper', 'Richard',
        'Robert', 'Roger', 'Romain', 'Romaric', 'Rodrigue', 'Serge', 'Séverin', 'Sylvain', 'Théodore',
        'Thierry', 'Timothée', 'Ulrich', 'Urbain', 'Valentin', 'Victor', 'Vincent', 'Wilfried', 'Yannick',
        'Yves', 'Zacharie', 'Ezéchiel', 'Ghislain', 'Josué', 'Fiacre', 'Gildas', 'Hermann', 'Kévin',
        'Aimé', 'Albert', 'Alphonse', 'André', 'Antoine', 'Armand', 'Bernard', 'Blaise', 'Bruno',
        'Casimir', 'Christophe', 'Elie', 'Fortuné', 'Gédéon', 'Hugues', 'Isaac', 'Jules', 'Lazare',
        'Modeste', 'Rufin', 'Samuel', 'Simon', 'Stéphane', 'Thomas', 'Abel', 'Achille', 'Merveil',
        'Fréjus', 'Gratien', 'Jordan', 'Ryan', 'Ange-Marie', 'Elisée',
    ];

    /** Prénoms masculins locaux (jour de naissance fon/ewe, yoruba, bariba...). */
    public const PRENOMS_LOCAUX_M = [
        'Codjo', 'Kossi', 'Kodjo', 'Komlan', 'Kokou', 'Koffi', 'Yao', 'Kouassi', 'Sèna', 'Mahougnon',
        'Sènami', 'Sèdjro', 'Dossou', 'Dossa', 'Houénou', 'Akanni', 'Babatoundé', 'Adébayo', 'Olouwafêmi',
        'Oluwaseun', 'Bio', 'Sabi', 'Orou', 'Gounou', 'Worou', 'Yarou', 'Kora', 'Chabi', 'Sêgla',
        'Ayessou', 'Mawuli', 'Elom', 'Kpatchavi', 'Djidjoho', 'Gbènakpon', 'Hounyo', 'Midokpè', 'Sourou',
        'Ayinla', 'Adéniyi',
    ];

    /** Prénoms masculins musulmans (familles nago, dendi, bariba). */
    public const PRENOMS_MUSULMANS_M = [
        'Rachidi', 'Moussa', 'Issa', 'Souleymane', 'Ibrahim', 'Mohamed', 'Abdou', 'Karim', 'Rafiou',
        'Lassissi', 'Saliou', 'Fataï', 'Moudachirou', 'Nouroudine', 'Yacoubou', 'Abdel-Aziz',
    ];

    /** Prénoms féminins d'usage (chrétiens / français). */
    public const PRENOMS_F = [
        'Marie', 'Marie-Claire', 'Marie-Josée', 'Marie-Madeleine', 'Marie-Reine', 'Marie-Noëlle', 'Anne',
        'Anne-Marie', 'Bernadette', 'Brigitte', 'Cécile', 'Christelle', 'Clarisse', 'Claudine', 'Colette',
        'Corinne', 'Dorcas', 'Edwige', 'Élise', 'Élodie', 'Emmanuella', 'Estelle', 'Eudoxie', 'Eugénie',
        'Fabiola', 'Félicité', 'Florence', 'Françoise', 'Gisèle', 'Grâce', 'Hortense', 'Huguette', 'Inès',
        'Irène', 'Jeanne', 'Joséphine', 'Judith', 'Julienne', 'Laure', 'Laurence', 'Lucie', 'Lydie',
        'Mélanie', 'Michèle', 'Monique', 'Nadège', 'Nathalie', 'Noëlie', 'Odile', 'Olga', 'Pascaline',
        'Patricia', 'Paulette', 'Perpétue', 'Prisca', 'Rachelle', 'Rebecca', 'Régine', 'Rosine', 'Ruth',
        'Sabine', 'Sandrine', 'Sarah', 'Sidonie', 'Solange', 'Sophie', 'Stéphanie', 'Sylvie', 'Thérèse',
        'Valérie', 'Véronique', 'Victoire', 'Viviane', 'Yolande', 'Yvette', 'Ange', 'Aurore', 'Bénédicte',
        'Blandine', 'Chimène', 'Gloria', 'Ornella', 'Rosemonde', 'Murielle', 'Carine', 'Armelle',
        'Arlette', 'Josiane', 'Ghislaine', 'Mireille', 'Pélagie', 'Reine', 'Raïssa', 'Tatiana', 'Larissa',
        'Floriane', 'Merveille', 'Divine', 'Exaucée', 'Gracia', 'Bénie', 'Espérance', 'Charlène',
        'Océane', 'Agnès', 'Albertine', 'Angèle', 'Béatrice', 'Catherine', 'Célestine', 'Chantal',
        'Christiane', 'Delphine', 'Élisabeth', 'Esther', 'Flora', 'Geneviève', 'Henriette', 'Isabelle',
        'Jacqueline', 'Léontine', 'Louise', 'Madeleine', 'Marthe', 'Micheline', 'Nicole', 'Philomène',
        'Rita', 'Rose', 'Suzanne', 'Victorine', 'Angélique', 'Ruth-Esther', 'Déborah', 'Priscille',
        'Jessica', 'Ingrid', 'Hermine', 'Eunice', 'Euphrasie', 'Clémence', 'Nina', 'Prudence',
    ];

    /** Prénoms féminins locaux. */
    public const PRENOMS_LOCAUX_F = [
        'Sèna', 'Fifamè', 'Mahougnon', 'Ayaba', 'Akouavi', 'Akossiwa', 'Afiavi', 'Adjoa', 'Abla', 'Ablavi',
        'Houéfa', 'Ahouéfa', 'Sènami', 'Dossi', 'Sika', 'Gbèmisola', 'Folake', 'Folashadé', 'Yetundé',
        'Jumoké', 'Bisola', 'Omolola', 'Adjovi', 'Assiba', 'Eyram', 'Enyonam', 'Mawussi', 'Ayélé',
        'Dédé', 'Nafissatou', 'Gniré', 'Bassira', 'Ifèoluwa', 'Akpénè', 'Dzifa',
    ];

    /** Prénoms féminins musulmans. */
    public const PRENOMS_MUSULMANS_F = [
        'Kafilath', 'Rafiatou', 'Zénabou', 'Mariama', 'Kouboura', 'Chakirath', 'Ramatou', 'Bintou',
        'Aïcha', 'Fatoumata', 'Rahinatou', 'Nafissath', 'Moutiath', 'Latifa',
    ];

    /** Quartiers de Cotonou et des communes voisines (adresse des parents). */
    public const QUARTIERS = [
        'Akpakpa' => 'Cotonou', 'Cadjèhoun' => 'Cotonou', 'Fidjrossè' => 'Cotonou', 'Gbégamey' => 'Cotonou',
        'Zogbo' => 'Cotonou', 'Agla' => 'Cotonou', 'Haie Vive' => 'Cotonou', 'Sainte-Rita' => 'Cotonou',
        'Vèdoko' => 'Cotonou', 'Mènontin' => 'Cotonou', 'Gbèdjromèdé' => 'Cotonou', 'Jéricho' => 'Cotonou',
        'Zongo' => 'Cotonou', 'Ganhi' => 'Cotonou', 'Dantokpa' => 'Cotonou', 'Missèbo' => 'Cotonou',
        'Sikècodji' => 'Cotonou', 'Houéyiho' => 'Cotonou', 'Vodjè' => 'Cotonou', 'Kouhounou' => 'Cotonou',
        'Aïdjèdo' => 'Cotonou', 'Dédokpo' => 'Cotonou', 'Sodjèatinmè' => 'Cotonou', 'Agontinkon' => 'Cotonou',
        'Gbèto' => 'Cotonou', 'Avotrou' => 'Cotonou', 'Tokpa-Hoho' => 'Cotonou', 'Ayélawadjè' => 'Cotonou',
        'Patte d\'Oie' => 'Cotonou', 'Cocotiers' => 'Cotonou', 'Djomèhountin' => 'Cotonou',
        'Godomey' => 'Abomey-Calavi', 'Calavi-Kpota' => 'Abomey-Calavi', 'Togba' => 'Abomey-Calavi',
        'Akassato' => 'Abomey-Calavi', 'Zogbadjè' => 'Abomey-Calavi', 'Tankpè' => 'Abomey-Calavi',
        'Cocotomey' => 'Abomey-Calavi', 'Atrokpocodji' => 'Abomey-Calavi', 'Arconville' => 'Abomey-Calavi',
        'Ouèdo' => 'Abomey-Calavi', 'Hêvié' => 'Abomey-Calavi', 'Womey' => 'Abomey-Calavi',
        'Ekpè' => 'Sèmè-Podji', 'PK10' => 'Sèmè-Podji', 'Agblangandan' => 'Sèmè-Podji',
        'Ouando' => 'Porto-Novo', 'Tokpota' => 'Porto-Novo',
    ];

    /** Professions des parents. */
    public const PROFESSIONS = [
        'Commerçante', 'Commerçant', 'Enseignant', 'Enseignante', 'Médecin', 'Infirmière', 'Sage-femme',
        'Ingénieur en génie civil', 'Fonctionnaire', 'Comptable', 'Avocat', 'Banquier', 'Banquière',
        'Transitaire', 'Couturière', 'Mécanicien', 'Entrepreneur', 'Pharmacien', 'Pharmacienne',
        'Militaire', 'Policier', 'Journaliste', 'Informaticien', 'Agent des douanes', 'Revendeuse',
        'Coiffeuse', 'Chauffeur', 'Architecte', 'Notaire', 'Magistrat', 'Agent immobilier',
        'Cadre administratif', 'Gestionnaire', 'Restauratrice', 'Pasteur', 'Agent de la SBEE',
        'Agent de la SONEB', 'Agent du Port autonome', 'Électricien', 'Menuisier', 'Agronome',
        'Consultant', 'Assistante de direction', 'Secrétaire', 'Professeur d\'université',
        'Opérateur économique', 'Agent de banque', 'Technicien de laboratoire', 'Commissaire aux comptes',
        'Chargé de projets (ONG)', 'Gendarme', 'Retraité', 'Ménagère',
    ];

    /**
     * Préfixes mobiles (après « 01 ») des opérateurs MTN, Moov et Celtiis.
     */
    public const PREFIXES_MOBILES = [
        '96', '97', '66', '67', '61', '62', '69', '90', '91', '51', '52', '53', '54', // MTN
        '94', '95', '98', '99', '60', '64', '65', '68', '55', '58',                   // Moov
        '40', '41', '42', '43', '44',                                                // Celtiis
    ];

    /** Plages d'adresses IP des opérateurs béninois (journaux de connexion). */
    public const PLAGES_IP = ['41.85', '41.138', '102.215', '137.255', '197.234', '154.66', '41.79', '196.192'];

    /** Clients de l'application mobile parents (en-tête User-Agent). */
    public const USER_AGENTS = [
        'Dart/3.4 (dart:io)', 'Dart/3.5 (dart:io)', 'Dart/3.6 (dart:io)',
        'okhttp/4.12.0', 'CCPA-Parents/2.3.1 (Android 13; TECNO Spark 10)',
        'CCPA-Parents/2.3.1 (Android 14; Samsung Galaxy A15)', 'CCPA-Parents/2.4.0 (Android 12; itel A70)',
        'CCPA-Parents/2.4.0 (iOS 17.5; iPhone 12)', 'CCPA-Parents/2.4.2 (Android 14; Infinix Hot 40)',
        'CCPA-Parents/2.4.2 (iOS 18.1; iPhone 13)',
    ];
}
