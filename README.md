# IAM Leads

Plugin WordPress de génération et de gestion de leads pour le Groupe IAM.

- **Version** : 1.5.1
- **Prérequis** : WordPress 6.0+, PHP 8.0+
- **Contexte** : thème Univet (RSTheme), builder Elementor, stack Docker + Nginx + WordPress FPM + MySQL 8.0

---

## Installation

Depuis le VPS, en copiant le dossier dans le conteneur WordPress :

```bash
cd /opt/groupeiam-wp

# 1. Copier le dossier décompressé dans le conteneur
docker compose cp iam-leads wordpress:/var/www/html/wp-content/plugins/

# 2. Rétablir le propriétaire attendu par WordPress
docker compose exec wordpress chown -R www-data:www-data /var/www/html/wp-content/plugins/iam-leads
```

Puis, dans wp-admin → **Extensions**, activer « IAM Leads ».

L'activation crée deux tables (`wp_iam_forms`, `wp_iam_leads`) et un premier formulaire
« Demande d'information — Groupe IAM » avec les champs demandés par la direction.

**Mise à jour depuis la 1.0.0** : remplacer les fichiers suffit. Au premier chargement de
wp-admin, le plugin détecte le changement de version, ajoute la colonne `reglages` et
normalise les formulaires existants. Aucune commande SQL à lancer, et les formulaires déjà
publiés continuent de s'afficher à l'identique (mode « une page » par défaut).

---

## Chart.js en local

Le tableau de bord utilise Chart.js **4.4.1, livré avec le plugin** dans
`assets/lib/chart.min.js`. Il n'y a aucun repli sur un CDN : un chargement distant
coûtait une résolution DNS, une poignée de main TLS et 200 Ko à chaque ouverture du
tableau de bord, sur un site déjà lent.

Si le fichier venait à manquer (copie partielle, `.gitignore` trop large), les
graphiques ne s'affichent pas et une ligne est écrite dans le journal des
intégrations, visible dans **Réglages → Journal**. Pour le remettre en place :

```bash
curl -o assets/lib/chart.min.js \
  https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js
```

---

## Utilisation

### Publier un formulaire

**IAM Leads → Formulaires** donne le shortcode de chaque formulaire :

```
[iam_form id="1"]
```

À coller dans un widget *Shortcode* d'Elementor, sur la page voulue.

Le HTML généré utilise les classes de grille du thème (`rs-grid`, `rs-grid-cols-2`,
`rs-gap-20`, `rs-mob-p:grid-cols-1`, `rs-col-span-2`, `form-btn-area`) et les variables
CSS d'Elementor pour les couleurs et les fontes.

### Constructeur de champs

Types disponibles : texte, e-mail, téléphone, liste déroulante, boutons radio, cases à
cocher, zone de texte, date, champ caché, consentement.

Pour chaque champ : étiquette, identifiant technique (généré automatiquement), texte
indicatif, obligatoire ou non, options (une par ligne), largeur pleine ou demie.

**L'étiquette est obligatoire** : c'est le texte vu par le visiteur, et c'est d'elle que
dérive l'identifiant technique sous lequel la réponse est stockée. Sans étiquette, un
champ n'a rien à quoi rattacher une réponse et ne peut pas être enregistré ; le
constructeur bloque l'envoi et désigne le champ concerné. Seul le séparateur d'étape en
est dispensé.

**Correspondance CRM** : ce réglage relie un champ à une colonne dédiée
(programme, source, pays…). C'est lui qui alimente les filtres de la liste des leads,
les graphiques du tableau de bord et l'envoi vers HubSpot. Un champ sans correspondance
est bien enregistré, mais n'apparaît que dans le détail du lead.

**Logique conditionnelle** : renseigner l'identifiant technique du champ déclencheur et
la valeur attendue. Exemple livré par défaut — « Précision réseau social » s'affiche
quand « source » vaut « Réseaux sociaux ». Un champ masqué n'est jamais obligatoire,
côté client comme côté serveur.

### Champ « Nom »

Le type **Nom (prénom + nom de famille)** produit un seul champ visuel contenant deux
saisies côte à côte. Les deux sous-libellés sont modifiables ; par défaut « Prénom » et
« Nom de famille ».

Techniquement, ce champ unique génère **deux** valeurs (`{identifiant}_prenom` et
`{identifiant}_nom`), mappées automatiquement sur les colonnes `prenom` et `nom`. Le
réglage « Correspondance CRM » est donc masqué pour ce type : il est implicite. La colonne
`nom_complet`, l'export CSV et l'envoi HubSpot fonctionnent sans configuration.

Sur mobile, les deux saisies passent l'une sous l'autre.

### Mode étape par étape

Dans **Affichage**, choisir « Étape par étape », puis placer des champs de type
**Séparateur d'étape** entre les groupes de questions. L'étiquette du séparateur devient
le titre de l'étape ; son texte d'introduction s'affiche juste en dessous.

- Les champs situés avant le premier séparateur forment une première étape sans titre.
- Sans aucun séparateur, le formulaire s'affiche sur une seule page : le mode « étapes »
  ne casse rien si les séparateurs n'ont pas encore été posés.
- Indicateur au choix : barre de progression, points numérotés, ou aucun.
- Les libellés des boutons retour et suivant sont configurables.

Trois comportements à connaître :

1. **Validation progressive** — chaque étape est validée avant de passer à la suivante,
   puis l'ensemble du formulaire est revalidé à l'envoi. Le serveur revalide toujours tout.
2. **Envoi unique** — les étapes masquées restent dans le DOM. Tout part en une seule
   requête à la fin : aucune donnée partielle n'est enregistrée.
3. **Étapes vides sautées** — si toutes les questions d'une étape sont masquées par la
   logique conditionnelle, la navigation l'ignore automatiquement dans les deux sens.

En cas d'erreur renvoyée par le serveur sur un champ d'une étape précédente, le
formulaire ramène l'utilisateur sur l'étape concernée.

### Couleur du bouton

Dans **Affichage**, deux sélecteurs règlent la couleur de fond et la couleur du texte du
bouton d'envoi. Le réglage est propre à chaque formulaire, et s'applique aussi au bouton
« Continuer » du mode étape par étape.

Laissés vides — ou remis à zéro par le bouton « Effacer » du sélecteur — les deux champs
font suivre au bouton la couleur principale du thème (`--e-global-color-primary`
d'Elementor, repli `#1b4b8f`). C'est le comportement par défaut : un formulaire sans
réglage n'émet aucun style en ligne et reste identique à ce qu'il était.

Techniquement, les couleurs sont posées en variables CSS (`--iam-bouton`,
`--iam-bouton-texte`) sur l'enveloppe du formulaire : la feuille de styles publique reste
seule maîtresse de la mise en forme. Toute valeur qui n'est pas un hexadécimal valide
(`#abc` ou `#aabbcc`) est ramenée à une chaîne vide à l'enregistrement.

### Plaquettes

**IAM Leads → Réglages → Plaquettes par programme** associe un PDF de la médiathèque à
chaque programme. Après envoi, le visiteur reçoit le lien correspondant au programme
qu'il a choisi. Sans plaquette configurée, le message « Notre équipe vous contactera
sous 48h » s'affiche.

La liste des programmes couvre les trois licences, le GBBA, les six masters, les quatre
MBA, le DBA et le BBA Pro ; il faut donc une plaquette par ligne. Les quatre déjà
disponibles dans la médiathèque : `LPBA_MINI.pdf`, `LPIG_MINI.pdf`,
`LICENCE_DROIT_MINI.pdf`, `GBBA_MINI.pdf`.

Les plaquettes sont indexées par l'intitulé exact du programme. Quand un intitulé
disparaît de `programmes()`, la mise à jour du plugin retire l'entrée correspondante du
réglage — le PDF, lui, reste dans la médiathèque.

### Capture UTM

Les paramètres `utm_source`, `utm_medium`, `utm_campaign`, `utm_content` et `utm_term`
sont mémorisés dans un cookie de 30 jours dès la première page visitée, côté serveur et
côté client. Le référent externe, la page du formulaire, l'IP et le navigateur sont
enregistrés à la soumission.

L'IP est lue en tenant compte du reverse proxy Nginx (`X-Real-IP`, `X-Forwarded-For`).
Vérifier que le bloc `location` transmet bien ces en-têtes :

```nginx
proxy_set_header X-Real-IP $remote_addr;
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
```

### Export

Le bouton **Exporter** de la liste des leads ouvre un panneau qui reprend les filtres
actifs et laisse choisir ce qui part dans le fichier.

**Colonnes.** Vingt-quatre colonnes sont disponibles, regroupées en quatre sections :
identité, demande, provenance, technique. Sept sont cochées par défaut — de quoi passer
des appels sans faire défiler l'écran. Les boutons *Tout*, *Rien* et *Défaut* servent de
raccourcis. La sélection et le format sont mémorisés par utilisateur : deux services aux
besoins différents ne s'écrasent pas.

Les colonnes **Adresse IP** et **Navigateur** sont des données personnelles identifiantes,
signalées comme telles dans le panneau et décochées par défaut.

**Détail des réponses.** Quand un formulaire est sélectionné dans les filtres, une case
supplémentaire remplace la colonne fourre-tout « Réponses complètes » par une colonne par
question. Seules les questions **sans correspondance** apparaissent : celles qui en ont
une alimentent déjà une colonne dédiée (« E-mail », « Programme »…) et les détailler ne
ferait que dupliquer l'information. C'est le réglage **Correspondance CRM** de chaque
champ qui établit ce lien, dans le constructeur de formulaires.

**Formats.** CSV ou XLSX.

- Le CSV est encodé en UTF-8 avec BOM et séparé par des points-virgules : Excel l'ouvre
  directement avec les accents corrects. Les valeurs qu'Excel interpréterait comme une
  formule sont neutralisées — les champs proviennent d'un formulaire public.
- Le XLSX est écrit sans dépendance externe (`class-xlsx-writer.php`) : en-tête figée et
  en gras, filtres automatiques, largeurs de colonnes. Toutes les cellules sont du texte,
  ce qui préserve les numéros de téléphone internationaux au prix du tri chronologique
  natif. Ce format nécessite l'extension PHP `zip` ; en son absence l'option disparaît de
  l'interface.

L'export est lu par lots de 500 leads : il n'y a pas de plafond au nombre de lignes.

---

## Intégrations

### E-mails et serveur d'envoi (SMTP)

Deux e-mails partent à chaque soumission : la **notification interne** vers les
destinataires renseignés dans les réglages, et l'**accusé de réception** au prospect, si
l'option est cochée et si un champ du formulaire porte la correspondance CRM « E-mail ».

**L'image Docker de WordPress n'embarque aucun service d'envoi.** Sans SMTP, `wp_mail()`
échoue : aucun e-mail ne part, ni ceux du plugin ni ceux de WordPress (réinitialisation de
mot de passe comprise). C'est la première chose à vérifier si rien n'arrive.

**Réglages → Serveur d'envoi (SMTP)** : serveur, port, chiffrement, identifiant, mot de
passe. Le branchement se fait sur `phpmailer_init`, donc il vaut pour tout le site.

| Chiffrement | Port habituel |
|---|---|
| STARTTLS | 587 |
| SSL/TLS | 465 |
| Aucun | 25 |

Le champ mot de passe n'est jamais réaffiché : le laisser vide conserve celui déjà
enregistré. Pour le garder hors de la table des options, définir dans `wp-config.php` :

```php
define( 'IAM_LEADS_SMTP_PASS', 'le-mot-de-passe' );
```

La constante prime alors sur la valeur en base.

**Bouton « Envoyer un e-mail de test »** : expédie un message aux destinataires internes
sans passer par un formulaire. Chaque envoi — test, notification, accusé de réception —
est tracé dans **Réglages → Journal**, avec le motif exact renvoyé par le serveur en cas
d'échec. Un envoi qui part mais n'arrive pas relève alors du domaine expéditeur : vérifier
les enregistrements SPF et DKIM.

### HubSpot

**Réglages → Intégration CRM** : cocher « Activer », choisir HubSpot, coller un jeton
d'application privée disposant du droit `crm.objects.contacts.write`.

Un échec est journalisé et replanifié automatiquement 5 minutes plus tard (une seule
nouvelle tentative). Le statut d'envoi apparaît sur la fiche de chaque lead.

Les propriétés `iam_programme`, `iam_source`, `iam_utm_*` etc. doivent exister comme
propriétés personnalisées dans HubSpot, sinon l'API les rejette.

### WhatsApp

Une notification d'équipe est un message **à l'initiative de l'entreprise**. WhatsApp
n'autorise le texte libre que dans les 24 h suivant un message reçu du destinataire :
au-delà, seul un **template approuvé** passe. Le plugin envoie donc des templates par
défaut ; le mode « texte libre » n'est là que pour tester à l'intérieur de la fenêtre.

**Étape 1 — Compte.** Créer une application sur developers.facebook.com, y ajouter le
produit WhatsApp, et rattacher un compte WhatsApp Business. Le numéro utilisé ne doit
pas être déjà actif sur l'application WhatsApp classique.

**Étape 2 — Template.** Dans le WhatsApp Manager, créer un template de catégorie
**Utility** (pas Marketing : moins cher et moins restrictif), en français, avec ce corps
exact — il est aussi affiché dans Réglages, prêt à copier :

```
🎓 Nouveau lead IAM
Nom : {{1}}
Programme : {{2}}
Téléphone : {{3}}
Pays : {{4}}
Source : {{5}}
Contact préféré : {{6}}
Reçu le : {{7}}
Consultez la fiche complète dans le tableau de bord IAM Leads.
```

La dernière ligne n'est pas décorative : Meta refuse les templates dont le corps se
termine par une variable. L'approbation prend de quelques minutes à 24 h.

**Étape 3 — Réglages du plugin.**

| Champ | Valeur |
|---|---|
| Mode d'envoi | Template approuvé |
| Numéros de l'équipe | format international sans `+`, séparés par des virgules |
| Identifiant du numéro | le Phone Number ID (Meta) ou l'Account SID (Twilio) |
| Jeton d'accès | jeton permanent de l'application |
| Nom du template | le nom exact approuvé, ou le ContentSid Twilio (`HX…`) |
| Langue | le code déclaré à la création (`fr`) |

**Étape 4 — Test.** Soumettre le formulaire une fois, puis ouvrir
**Réglages → Journal des envois**. Le détail de la réponse de Meta y figure en cas
d'échec, et le statut d'envoi apparaît sur la fiche du lead.

**Coût.** Depuis le 1er juillet 2025, la facturation est au message délivré. Les
templates utilitaires envoyés dans une fenêtre de service ouverte sont gratuits ; ceux
envoyés en dehors — c'est le cas ici — sont facturés au tarif utilitaire du pays du
destinataire. Multiplier le volume de leads par le nombre de destinataires pour estimer
la dépense, et vérifier le tarif Sénégal sur la grille Meta en vigueur.

**Alternative.** Si l'approbation Meta est un frein, la notification par e-mail est déjà
opérationnelle sans configuration ni coût : il suffit de renseigner les adresses dans
Réglages. Elle couvre le même besoin d'alerte, avec le lien direct vers la fiche du lead.

### Ajouter un autre fournisseur

Les deux intégrations sont modulaires. Pour brancher Salesforce, Airtable ou un
fournisseur WhatsApp local, il suffit d'étendre la classe abstraite et de s'enregistrer
via un filtre — sans toucher au reste du plugin :

```php
add_filter( 'iam_leads_crm_fournisseurs', function ( $liste ) {
	$liste[] = new Mon_CRM_Airtable(); // étend IAM_Leads_CRM_Fournisseur
	return $liste;
} );
```

Le nouveau fournisseur apparaît alors dans la liste déroulante des réglages.

---

## Sécurité

- Nonce WordPress sur toutes les soumissions et toutes les actions d'administration
- `sanitize_*()` à l'entrée, `esc_*()` à la sortie, requêtes préparées via `$wpdb->prepare()`
- Capacité `manage_options` requise pour l'ensemble de l'administration
- Anti-robot : pot de miel + délai minimal configurable entre affichage et envoi
- Appels externes (HubSpot, WhatsApp) plafonnés à 8 secondes pour ne pas retarder la
  réponse au visiteur ; les échecs sont journalisés dans **Réglages → Journal des envois**

---

## Structure

```
iam-leads/
├── iam-leads.php                    Amorçage, constantes, création des tables
├── includes/
│   ├── class-iam-leads.php          Classe principale, réglages, assets
│   ├── class-form-builder.php       CRUD des formulaires, formulaire par défaut
│   ├── class-form-renderer.php      Shortcode, rendu HTML, traitement AJAX
│   ├── class-lead-manager.php       Stockage, requêtes filtrées, statuts
│   ├── class-utm-capture.php        Cookie UTM, référent, IP derrière proxy
│   ├── class-dashboard.php          Agrégations pour les graphiques
│   ├── class-export.php             Registre de colonnes, export CSV et XLSX
│   ├── class-xlsx-writer.php        Écriture OOXML sans dépendance
│   ├── class-crm-integration.php    CRM modulaire + HubSpot
│   ├── class-whatsapp.php           WhatsApp modulaire + Meta et Twilio
│   ├── class-emails.php             Notification interne et accusé de réception
│   └── class-logger.php             Journal des envois
├── admin/
│   ├── admin-menu.php               Menus, routage, actions
│   ├── views/                       Vues d'administration
│   ├── css/admin.css
│   └── js/                          form-builder.js, dashboard.js, settings.js
├── assets/
│   ├── css/iam-leads.css            Styles publics compatibles Univet
│   ├── js/                          iam-forms.js, utm-capture.js
│   └── lib/                         chart.min.js (à déposer)
└── languages/
```

---

## Point à valider avant mise en production

Les intitulés de la liste « Programme souhaité » sont définis dans
`includes/class-form-builder.php`, méthode `programmes()`. Ils doivent être confirmés
mot pour mot avec ceux publiés sur groupeiam.com : cette valeur se retrouve ensuite dans
le CRM, dans l'export CSV et dans tous les graphiques. Une fois des leads enregistrés,
changer un intitulé casse le regroupement des anciennes données.

Pour modifier la liste : éditer `programmes()`, puis incrémenter `IAM_LEADS_VERSION`
(en-tête et constante de `iam-leads.php`). Au premier passage dans l'admin, le plugin
réécrit les options du champ relié à « programme » dans tous les formulaires existants
— les options sont figées en base à la création du formulaire, sans cette étape la
modification ne serait visible que sur les formulaires créés ensuite. Le champ
« Programme associé » d'un formulaire, lui, n'est pas migré : si sa valeur a disparu de
la liste, le sélecteur retombe sur « — Aucun — » et il faut le repositionner à la main.

## Journal des versions

### 1.5.3
- Formulaires multi-étapes : au chargement de la page, le premier affichage ne fait plus défiler la page jusqu'au formulaire et ne place plus le focus dans le premier champ (le défilement et le focus restent actifs lors des changements d'étape).
