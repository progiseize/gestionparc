jQuery(document).ready(function(){

	// TOGGLE VIEW EMPTY PARCS
	jQuery('input[name="view_empty_parc"]').on('change',function(e){ parentForm = jQuery(this).parent('form'); parentForm.submit(); });

	//
	jQuery('.gp-eye-icon i').on('click',function(e){
		var parentable = jQuery(this).closest('table');
		if(jQuery(this).hasClass('fa-eye')){
			console.log('eye');
			jQuery(this).removeClass('fa-eye').addClass('fa-eye-slash');
			parentable.find('.gp-parc-hidden').removeClass('gp-parc-hidden');
		} else {
			console.log('eye-slash');
			jQuery(this).removeClass('fa-eye-slash').addClass('fa-eye');
			parentable.find('.liste_titre').addClass('gp-parc-hidden');
			parentable.find('.gestionparc-newline').addClass('gp-parc-hidden').removeAttr('style');
			parentable.find('.gestionparc-line').addClass('gp-parc-hidden');
		}
		//console.log(parentable.attr('id'));
	});

	// SHOW NEW LINE
	jQuery('.gestionparc-table .gestionparc-add').on('click',function(e){jQuery(this).closest('.gestionparc-table').find('.gestionparc-newline').toggle();});
	
	// SELECT 2
	jQuery('.gp-slct-simple').each(function(e){jQuery(this).select2({placeholder: 'Choisir dans la liste',language: {noResults: function(){return "Aucun résultat";}}});});
	jQuery('.gp-slct-simple-tags').each(function(e){jQuery(this).select2({placeholder: 'Choisir dans la liste',tags : true,language: {noResults: function(){return "Aucun résultat";}}});});
	jQuery('.gp-slct-multi-tags').each(function(e){jQuery(this).select2({placeholder: 'Saisir les valeurs séparés par des ,',tags : true,tokenSeparators: [','],language: {noResults: function(){return "Saisir les valeurs séparés par des ,";}}});});



});