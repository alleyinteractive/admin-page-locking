(($) ->
	class AdminPageLocking
		constructor: ->
			@currentTime = 0
			@lockPeriod = adminPageLockingData.lockPeriod * 1000
			@lockPeriodMax = adminPageLockingData.lockPeriodMax * 1000

			if $( '#apl-user' ).length
				alert $('#apl-lock-error p').text().trim()
				@exitScreen()
				return

			$( window ).on 'beforeunload', =>
				@releaseLock()
				return;

			$( document ).on 'click', '.apl-confirm-button', (event) =>
				tb_remove();
				$( document ).trigger 'apl-confirm', [ $( event.target ).data( 'confirm' ) ]

			@updateLock()
			@setMaxTimer()

		updateLock: ->
			@currentTime += @lockPeriod
			if @currentTime < @lockPeriodMax
				$.post(
					adminPageLockingData.ajaxUrl,
					{ action: adminPageLockingData.actionUpdateLock },
					(response) ->
						unless response.success
							alert response.data.message
							location.reload()
				);
				@setLockTimer()

		releaseLock: ->
			# If the nonce is invalid, we don't bother alerting the user because
			# they're leaving the page anyway.
			$.post(
				adminPageLockingData.ajaxUrl,
				{ action: adminPageLockingData.actionReleaseLock }
			);

		# @todo this uses a modal to confirm because a traditional confirm dialog
		#       blocks execution. Therefore, if someone stayed on the page for an
		#       hour with the confirmation message up, and clicked "yes" that they
		#       want more time, they wouldn't get it -- that's a crumy UX. However,
		#       the modal box won't pull attention to this window/tab like a
		#       confirm dialog would, so it's possible that someone would miss the
		#       message even though they do need more time. Need to make a
		#       decision on that.
		#
		askForMoreTime: ->
			promptTimer = setTimeout =>
				alert adminPageLockingData.errorLockMax
				@exitScreen()
			, @lockPeriod

			respondToConfirm = ( event, response ) =>
				$( document ).off( 'apl-confirm', respondToConfirm );
				clearTimeout( promptTimer )
				if 'yes' is response
					@currentTime = 0
					@setMaxTimer()
					@setLockTimer()
				else
					@exitScreen()

			$( document ).on( 'apl-confirm', respondToConfirm );

			backupResponse = @modalConfirm adminPageLockingData.moreTime

			# If thickbox isn't available, a conventional "confirm" dialog is used.
			# If that happens, respond synchronously.
			if -1 isnt backupResponse
				@respondToConfirm null, ( if backupResponse then 'yes' else 'no' )

		setMaxTimer: ->
			clearTimeout @maxTimer
			@maxTimer = setTimeout =>
				@askForMoreTime()
			, @lockPeriodMax - @lockPeriod

		setLockTimer: ->
			clearTimeout @lockTimer
			@lockTimer = setTimeout =>
				@updateLock()
			, @lockPeriod

		exitScreen: ->
			location.href = adminPageLockingData.adminUrl

		modalAlert: (message) ->
			if tb_show?
				unless $( '#apl-message-content' ).length
					$( 'body' ).append '<div id="apl-message" style="display:none;"><p id="apl-message-content"></p></div>'

				$( '#apl-message-content' ).text message
				tb_show null, '#TB_inline?inlineId=apl-message&width=300&height=200', false
			else
				alert message

		modalConfirm: (message) ->
			if tb_show?
				unless $( '#apl-message-content' ).length
					$( 'body' ).append(
						'<div id="apl-message" style="display:none;">' +
							'<p id="apl-message-content"></p>' +
							'<p><a class="button-primary apl-confirm-button" data-confirm="yes">Yes</a>' +
							'<a class="button-secondary apl-confirm-button" data-confirm="no">No</a></p>' +
						'</div>'
					)

				$( '#apl-message-content' ).text message
				tb_show null, '#TB_inline?inlineId=apl-message&width=300&height=200', false
				return -1
			else
				return confirm message

	$ ->
		new AdminPageLocking()

) jQuery