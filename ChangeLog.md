## GestionParc

[comment]: <> (TODO)
[comment]: <> (Modele pdf)
[comment]: <> (Harmoniser les constantes du module)

### 1.9.0
- NEW : Revert mode on verifications - a checked component can be un-checked in one click during an ongoing verification (the green "checked" button turns into "undo check" on hover); the verified counter is kept in sync. Works in card and list views, instant and manual modes.
- NEW : When closing a verification with components left uncontrolled, a Yes/No confirmation pop-up ("Warning, some components have not been controlled. Do you want to continue?") is shown.
- MAJ : PDF export now starts each component (organe) on a new page, so a table is no longer split across pages when avoidable (a single table larger than one page still spans, by necessity); the first component stays under the header on page 1.
- FIX : PDF export - a table spanning several pages now keeps its navy outer border on every page and repeats the column headers on each page (previously the border was missing on large tables).
- FIX : PDF export - row heights now match the actual rendering (computed from the real number of wrapped lines), fixing truncated cells, uneven row spacing and a stray artifact left under a table at a page break.
- FIX : Excel export - long header labels (e.g. "Référentiel de conformité") now wrap inside the label column instead of being cut.
- MAJ : Excel export - all tables now share the same fixed total width (columns distributed to a constant width instead of auto-sized), including the consolidated sheet where shorter components fill the width by merging their last column. Text columns wrap and row height adapts to the content, so nothing is cut or overflows.
- NEW : Each verification now stores a snapshot of its park data at close time; Excel/PDF exports of a past verification reflect the data as it was then, no longer the current live data. Verifications closed before this version (no snapshot) still fall back to live data.
- NEW : Repair page can rebuild snapshots for past verifications from the historical XLSX reports stored in each intervention (preview + apply); exports of old verifications then show the original header (technician/sales rep/client) and items. Also available via scripts/backport_snapshots.php.
- MAJ : "Force default value on verification" now also resets a field whose default value is empty (the field is cleared) - e.g. to wipe the "Observations" field at each new verification.
- NEW : Park items are now displayed in numbering order (autonumber field) by default; only items moved manually (drag & drop) keep their fixed position. New items are inserted at their numbering position instead of at the end. Dragging a pinned item back to its numbering position automatically un-pins it.
- MAJ : Migration preserves existing manual arrangements - parcs whose saved order already matched their creation order are switched to numbering order, while parcs that had been reordered manually keep their layout (items pinned).
- NEW : "Compliance framework" field (APSAD R4 / Code du Travail) added to the thirdparty GestionParc tab, editable inline (pencil).
- NEW : At least one compliance framework must be checked on the thirdparty to close (validate) a verification.
- NEW : The compliance framework is snapshotted onto the intervention extrafield at verification close (visible/editable on the intervention card).
- MAJ : Excel and PDF exports now display the compliance framework at the bottom of the header (read from the intervention snapshot, fallback to the thirdparty).
- NEW : "Plan type" field (Plan de sécurité / Plan d'intervention / Plan d'évacuation / Sans) with the exact same behaviour as the compliance framework (thirdparty tab, validation requirement, intervention snapshot, exports header).
- NEW : Sales representative and technician are now stored as intervention extrafields, set at verification close and editable inline (pencil) per field from the GestionParc tab and the intervention card.
- NEW : Defaults at verification start - sales rep and technician are both carried over from the previous intervention (so launching a verification no longer overrides the displayed choice); fallback to the thirdparty's current sales rep / the current user when empty.
- NEW : Existing interventions are backfilled (empty values only) - technician from the validator, sales rep from the thirdparty's current sales rep.
- MAJ : Excel and PDF exports now rely on these stored values (fallback to legacy behaviour when empty).
- FIX : Export title now shows the intervention year instead of the current year.

### 1.8.4
- NEW : Card edit mode during verifications is now loaded via AJAX to prevent page reloads.
- NEW : Cloned cards now automatically open in edit mode to easily apply changes.
- FIX : Added auto-incrementation for the autonumber field when cloning cards.
- NEW : Added custom export templates (Excel & PDF)

### 1.8.3
- NEW: Added 'Force default value on verification' option for parc fields to automatically reset specified fields to their default value when opening a new verification session
- NEW : Added option "N/C" to the year selection list field

### 1.8.2
- MAJ: The "Numero" field now supports both text and numbers, instead of only numbers. This provides more flexibility for entering custom identifiers or alphanumeric codes for park items.
- MAJ: The default view (card or list) for displaying parks on third parties is now configurable at the module level. The selected view is enforced for all users, improving consistency.
- NEW: Added a "Verification mode" configuration setting, allowing admins to choose between two verification workflow options:
  - **Instant**: Items are verified instantly upon clicking.
  - **Manual**: Items must be manually opened in edit mode and validated, ensuring all required data is checked before verification.
- NEW: Added an option in module configuration to enable or disable the "Verify All" button during a verification session.
- NEW: Added a "Mandatory in manual verification" option for fields: when designing verification fields, you can now specify if a field must be completed when in manual verification mode (even if it's only visible during verification). This ensures critical information is collected during manual control processes.
- FIX : Fix crash during verification validation when Excel export is enabled and no items have Excel export enabled on the verification.


### 1.8.1
- FIX : Alter existing llx_gestionparc__* tables to add missing 'position' column
- MAJ : Add anchor system on tabs/gestionparc.php to prevent the page from scrolling back to the top on each reload when a JavaScript action is performed
- FIX : Add missing 'gp_verif_success_oncancel' translation key
- MAJ : UX enhancements on responsive version (mobile) of tabs/gestionparc.php (bigger clicking areas on card buttons)
- FIX : Cards are now open by default in tabs/gestionparc.php, fixing the need to click on each one to open
- FIX : Fix for a bug that, in certain cases, prevented the 'Parc client' tab from being displayed on third-party page
- FIX : Fix Select2 search bars not working in formconfirm dialogs (add item popup)
- MAJ : Improved the verification process with an AJAX workflow to avoid reloading the page on each item verification


### 1.8.0
- MAJ: Limit tabs to 5
- FIX: Undefined variable $nb_val
- FIX: Auto number even if field is disabled
- MAJ: Module tab by actions, not by descriptor
- MAJ: Add more complex rights
- MAJ: New field active by default
- FIX: DEPRECATED Creation of dynamic property ActionsGestionParc
- NEW: New Cards view for items and ajax calls
- NEW: en_US language (made with automatic traduction)

### 1.7.4
- FIX: Remove verif warning
- FIX: Fix errors with date field if empty

### 1.7.3
- FIX: Remove check module version (Bad practice)
- FIX: Remove trailing spaces

### 1.7.2
- FIX: Fix redundant SQL queries in the homepage widget to improve page load speed

### 1.7.1
- FIX: Fix FichInter validation

### 1.7.0
- NEW: Input type date

### 1.6.2
- MAJ: Add info for parc key if advanced exports is active (Copy to Clipboard Icon)
- FIX: Count excel fields by value 1

### 1.6.1
- MAJ: Advanced Exports: Add email address
- NEW: Advanced Exports: Align field with const GESTIONPARC_EXCEL_ALIGN_[FIELDKEY] and values left/center/right
- NEW: Advanced Exports: Group the lines in packs of X lines
- NEW: Advanced Exports: Add border to empty group lines with GESTIONPARC_ADVANCED_EXPORT_FILLEMPTY 
- MAJ: Advanced Exports: Add borders to parcset and automerge cells after E
- MAJ: Add missing FR translations
- FIX: Fix error : Add key value Verif DB field

### 1.6.0
- NEW: Advanced Exports in Excel format
- FIX: Remove empty lines & console logs

### 1.5.1
- FIX: Load langs in loadBox 

### 1.5.0
- NEW: Détails de la vérification sur les fiches d'intervention
- MAJ: Mise à jour de la page setup

### 1.4.2
- NEW: Nouveau droit lecture (différenciation droits gestion & lecture)

### 1.4.1
- FIX: Var name $results_prodserv (GestionParcGetListProdServ())
- NEW: Possibilité de vérifier tous les éléments d'un parc pour les admins seulement  

### 1.4.0
- FIX: Code review with PHPCS 

### 1.3.9
- NEW: Option champ visible uniquement en mode vérif 

### 1.3.8 (03/10/2023) 
- FIX: Fix for V18 Module Export
- FIX: get all products without categories

### 1.3.7 (08/09/2023) 
- NEW: Add a repair page for repair retro-compatibility admin/repair.php
- FIX: modification action add Tab Parc
- FIX: Restriction for field name

### 1.3.6 (23/05/2023) 
- MAJ: Corrections Descripteur module
- FIX: Corrections tabs si aucun parc créé ou activé
- FIX: Correction assignation catégorie de tiers
- FIX: Correction creation table SQL llx_gestionparc_fields
- FIX: Correction compatibilité PHP < 7.4
- MAJ: Mise à jour CSS
- FIX: DEFAULT VALUE for verif

### 1.3.5 (01/03/2023) 
- MAJ: CSV séparateur par défault ';'
- MAJ: CSV : Si produit ou service, ajoute une colonne avec le label du produit/service

### 1.3.4 (07/02/2023) 
- MAJ: Update Lang

### 1.3.3 (01/02/2023) 
- FIX: SQL, ajout DEFAULT 0 sur author_maj

### 1.3.2 (01/06/2022) 
- FIX: Ajout valeur par defaut pour author_maj (SQL)

### 1.3.1 (01/06/2022) 
- NEW: Affichage mises à jour pages modules

### 1.3 (23/05/2022)
- NEW: Activation / Désactivation des parcs
- NEW: Nouvelle interface à onglets
- MAJ: Champ désactivé par défaut lors de la création
- MAJ: Fichiers CSV: En attente du modèle en cours de dev

### 1.2 (04/05/2022)
- NEW: fusion des parcs et des interventions lors de la fusion de tiers
- NEW: Option pour saisie des heures par l'utilisateur ou en automatique.

### 1.1 (16/03/2022)
- MAJ: Le bouton vérification n'est plus visible si l'ensemble des parcs sont vides
- NEW: Il est possible d'annuler une vérification
- NEW: Possibilité de masquer le contenu des parcs
- NEW: Option pour redirection vers les fiches d'interventions générées

### 1.0 (16/03/2022)
- Module permettant de créer des parcs clients et créer des interventions sur ces parcs.
- Traductible  100%
- Box Accueil : Suivi du nombre de parcs, nombre total d'items
