;( function( $ ) {
	/**
	 * 1. Every adminPageLockingData.lockPeriod seconds, update the lock
	 * 2. After adminPageLockingData.lockPeriodMax seconds, ask the user if they need more time?
	 *     a. After adminPageLockingData.lockPeriod seconds of no response, kill it
	 *         - TEST: can we redirect if confirm dialog is open?
	 *     b. If user requests more time, refresh adminPageLockingData.lockPeriodMax
	 * 3. If the screen is loaded and in use, submit a notice and redirect to dashboard
	 */

	var AdminPageLocking = function() {
		var self = this;

		self.maxTimer;
		self.lockTimer;
		self.currentTime = 0;

		if ( $( '#apl-user' ).length ) {
			alert( adminPageLockingData.errorLock );
			self.exitScreen();
		}

		$( document ).on( 'click', '.apl-confirm-button', function() {
			tb_remove();
			$( document ).trigger( 'apl-confirm', [ $( this ).data( 'confirm' ) ] );
		});

		self.updateLock();
		self.setMaxTimer();
	};

	AdminPageLocking.prototype.updateLock = function() {
		self.currentTime += adminPageLockingData.lockPeriod;
		if ( self.currentTime < adminPageLockingData.lockPeriodMax ) {
			$.post(
				adminPageLockingData.ajaxUrl,
				{ action: adminPageLockingData.actionUpdateLock },
				function( response ) {
					if ( ! response.success ) {
						alert( response.data.message );
						location.reload();
					}
				}
			);
			self.setLockTimer();
		}
	};

	AdminPageLocking.prototype.releaseLock = function() {
		// If the nonce is invalid, we don't bother alerting the user because
		// they're leaving the page anyway.
		$.post(
			adminPageLockingData.ajaxUrl,
			{ action: adminPageLockingData.actionReleaseLock }
		);
	};

	/**
	 * @todo this uses a modal to confirm because a traditional confirm dialog
	 *       blocks execution. Therefore, if someone stayed on the page for an
	 *       hour with the confirmation message up, and clicked "yes" that they
	 *       want more time, they wouldn't get it -- that's a crumy UX. However,
	 *       the modal box won't pull attention to this window/tab like a
	 *       confirm dialog would, so it's possible that someone would miss the
	 *       message even though they do need more time. Need to make a
	 *       decision on that.
	 */
	AdminPageLocking.prototype.askForMoreTime = function() {
		var promptTimer = setTimeout( exitScreen, adminPageLockingData.lockPeriod );

		var respondToConfirm = function( event, response ) {
			$( document ).off( 'apl-confirm', respondToConfirm );
			if ( 'yes' === response ) {
				clearTimeout( promptTimer );
				self.currentTime = 0;
				self.setMaxTimer();
				self.setLockTimer();
			} else {
				self.exitScreen();
			}
		};
		$( document ).on( 'apl-confirm', respondToConfirm );

		backupResponse = self.modalConfirm( adminPageLockingData.moreTime );

		// If thickbox isn't available, a conventional "confirm" dialog is used.
		// If that happens, respond synchronously.
		if ( -1 !== backupResponse ) {
			self.respondToConfirm( null, ( backupResponse ? 'yes' : 'no' ) );
		}
	};

	AdminPageLocking.prototype.setMaxTimer = function() {
		clearTimeout( self.maxTimer );
		self.maxTimer = setTimeout( self.askForMoreTime, ( adminPageLockingData.lockPeriodMax - adminPageLockingData.lockPeriod ) * 1000 );
	};

	AdminPageLocking.prototype.setLockTimer = function() {
		clearTimeout( self.lockTimer );
		self.lockTimer = setTimeout( self.updateLock, adminPageLockingData.lockPeriod * 1000 );
	};

	AdminPageLocking.prototype.exitScreen = function() {
		location.href = adminPageLockingData.adminUrl;
	};

	AdminPageLocking.prototype.modalAlert = function( message ) {
		if ( 'undefined' !== typeof tb_show ) {
			if ( ! $( '#apl-message-content' ).length ) {
				$( document ).append( '<div id="apl-message" style="display:none;"><p id="apl-message-content"></p></div>' );
			}
			$( '#apl-message-content' ).text( message );
			tb_show( null, '#TB_inline?inlineId=apl-message', false );
		} else {
			alert( message );
		}
	};

	AdminPageLocking.prototype.modalConfirm = function( message ) {
		if ( 'undefined' !== typeof tb_show ) {
			if ( ! $( '#apl-message-content' ).length ) {
				$( document ).append(
					'<div id="apl-message" style="display:none;">' +
						'<p id="apl-message-content"></p>' +
						'<p><a class="button-primary apl-confirm-button" data-confirm="yes">Yes</a>' +
						'<a class="button-secondary apl-confirm-button" data-confirm="no">No</a></p>' +
					'</div>'
				);
			}
			$( '#apl-message-content' ).text( message );
			tb_show( null, '#TB_inline?inlineId=apl-message', false );
			return -1;
		} else {
			return confirm( message );
		}
	};

	$( function() {
		new AdminPageLocking();
	});

})( jQuery );
