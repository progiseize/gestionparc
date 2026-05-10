// Fonction pour initialiser Select2 sur les éléments
function initGestionParcSelect2(context, forceReinit) {
	var $context = context ? jQuery(context) : jQuery(document);
	
	// Déterminer le conteneur pour le dropdown
	var dropdownContainer = jQuery('body');
	if (forceReinit) {
		// Dans une dialog, chercher le conteneur .ui-dialog
		var $uiDialog = $context.closest('.ui-dialog');
		if ($uiDialog.length > 0) {
			dropdownContainer = $uiDialog;
		}
	}
	
	$context.find('.gp-slct-simple, .gp-slct-simple-tags, .gp-slct-multi-tags').each(function(){
		var $select = jQuery(this);
		var isSimple = $select.hasClass('gp-slct-simple');
		var isSimpleTags = $select.hasClass('gp-slct-simple-tags');
		var isMultiTags = $select.hasClass('gp-slct-multi-tags');
		
		// Détruire si déjà initialisé et qu'on force la réinit (dialog)
		if (forceReinit && $select.hasClass('select2-hidden-accessible')) {
			$select.select2('destroy');
		}
		
		// Initialiser si pas encore fait
		if (!$select.hasClass('select2-hidden-accessible')) {
			var config = {
				language: {noResults: function(){return "Aucun résultat";}},
				dropdownParent: dropdownContainer
			};
			
			if (isSimple) {
				config.placeholder = 'Choisir dans la liste';
			} else if (isSimpleTags) {
				config.placeholder = 'Choisir dans la liste';
				config.tags = true;
			} else if (isMultiTags) {
				config.placeholder = 'Saisir les valeurs séparés par des ,';
				config.tags = true;
				config.tokenSeparators = [','];
			}
			
			$select.select2(config);
		}
	});
}

jQuery(document).ready(function(){

	// SELECT 2 - Initialisation au chargement de la page
	initGestionParcSelect2();

	// SELECT 2 - Réinitialisation dans les dialogs
	jQuery(document).on('dialogopen', function(event) {
		setTimeout(function() {
			initGestionParcSelect2(jQuery(event.target), true);
		}, 150);
	});

	/*// TOGGLE VIEW EMPTY PARCS
	jQuery('input[name="view_empty_parc"]').on('change',function(e){ parentForm = jQuery(this).parent('form'); parentForm.submit(); });

	//
	jQuery('.gp-eye-icon i').on('click',function(e){
		var parentable = jQuery(this).closest('table');
		if(jQuery(this).hasClass('fa-eye')){
			//console.log('eye');
			jQuery(this).removeClass('fa-eye').addClass('fa-eye-slash');
			parentable.find('.gp-parc-hidden').removeClass('gp-parc-hidden');
		} else {
			//console.log('eye-slash');
			jQuery(this).removeClass('fa-eye-slash').addClass('fa-eye');
			parentable.find('.liste_titre').addClass('gp-parc-hidden');
			parentable.find('.gestionparc-newline').addClass('gp-parc-hidden').removeAttr('style');
			parentable.find('.gestionparc-line').addClass('gp-parc-hidden');
		}
		//console.log(parentable.attr('id'));
	});

	// SHOW NEW LINE
	jQuery('.gestionparc-table .gestionparc-add').on('click',function(e){jQuery(this).closest('.gestionparc-table').find('.gestionparc-newline').toggle();});
	*/

	// --- CUSTOM EXPORT MODAL LOGIC ---
	
	// Open Modal
	jQuery(document).on('click', '.gp-open-export-modal', function(e) {
		e.preventDefault();
		var interId = jQuery(this).data('id');
		if (!interId) return;

		// Update Links
		jQuery('#gp-btn-excel-confirm').attr('href', GP_EXPORT_BASE_URL + interId);
		jQuery('#gp-btn-pdf-confirm').attr('href', GP_EXPORT_PDF_BASE_URL + interId);

		// Show Modal
		jQuery('#gp-export-modal-overlay').fadeIn(200);
		jQuery('body').css('overflow', 'hidden'); // Prevent scroll

	});

	// Close Modal (Close buttons + Confirm buttons)
	jQuery(document).on('click', '.gp-modal-close, .gp-modal-close-btn, #gp-btn-excel-confirm, #gp-btn-pdf-confirm', function() {
		jQuery('#gp-export-modal-overlay').fadeOut(200);
		jQuery('body').css('overflow', '');
	});



	// Close Modal (Overlay click)
	jQuery(document).on('click', '#gp-export-modal-overlay', function(e) {
		if (jQuery(e.target).hasClass('gp-modal-overlay')) {
			jQuery(this).fadeOut(200);
			jQuery('body').css('overflow', '');
		}
	});

});