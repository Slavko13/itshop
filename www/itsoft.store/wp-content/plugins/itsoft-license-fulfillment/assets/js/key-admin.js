( function () {
	'use strict';

	const form = document.getElementById( 'itsoft-lf-key-management' );
	if ( ! form ) {
		return;
	}

	const selectAll = document.getElementById( 'itsoft-lf-select-all' );
	const checkboxes = Array.from( form.querySelectorAll( '.itsoft-lf-key-checkbox' ) );
	const count = document.getElementById( 'itsoft-lf-selected-count' );
	const action = document.getElementById( 'itsoft-lf-key-action' );
	const targetWrap = document.getElementById( 'itsoft-lf-target-pool-wrap' );
	const target = document.getElementById( 'itsoft-lf-target-pool' );

	function updateCount() {
		const selected = checkboxes.filter( ( checkbox ) => checkbox.checked ).length;
		count.textContent = 'Выбрано: ' + selected;
		if ( selectAll ) {
			selectAll.checked = checkboxes.length > 0 && selected === checkboxes.length;
			selectAll.indeterminate = selected > 0 && selected < checkboxes.length;
		}
	}

	function updateTarget() {
		const isMove = action.value === 'move';
		targetWrap.hidden = ! isMove;
		target.required = isMove;
	}

	if ( selectAll ) {
		selectAll.addEventListener( 'change', function () {
			checkboxes.forEach( ( checkbox ) => {
				checkbox.checked = selectAll.checked;
			} );
			updateCount();
		} );
	}

	checkboxes.forEach( ( checkbox ) => checkbox.addEventListener( 'change', updateCount ) );
	action.addEventListener( 'change', updateTarget );
	form.addEventListener( 'submit', function ( event ) {
		if ( ! checkboxes.some( ( checkbox ) => checkbox.checked ) ) {
			event.preventDefault();
			window.alert( 'Выберите хотя бы один ключ.' );
		}
	} );

	updateCount();
	updateTarget();
}() );
