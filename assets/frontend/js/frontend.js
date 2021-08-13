jQuery(
	function ( $ ) {

		if ( typeof wpheka_rfq_frontend_params === 'undefined' ) {
			return false;
		}

		/**
		 * RfqFrontendHandler class.
		 */
		var RfqFrontendHandler = function () {
			this.requests   = [];
			this.addRequest = this.addRequest.bind( this );
			this.run        = this.run.bind( this );

			$( document.body )
			.on( 'click', '.add-to-quote-single', { RfqFrontendHandler: this }, this.onAddToQuote )
			.on( 'click', '.wpheka-quote-product-list-form .product-remove > a', { RfqFrontendHandler: this }, this.onRemoveFromQuote );

			$( document ).on( 'submit', 'form#wpheka-quote-request-list-form', { RfqFrontendHandler: this }, this.onUpdateQuoteList );
			$( document ).on( 'submit', 'form#wpheka-quote-request-form', { RfqFrontendHandler: this }, this.onSendRequestList );
		};

		/**
		 * Add add to quote event.
		 */
		RfqFrontendHandler.prototype.addRequest = function ( request ) {
			this.requests.push( request );

			if ( 1 === this.requests.length ) {
				this.run();
			}
		};

		/**
		 * Run add to quote events.
		 */
		RfqFrontendHandler.prototype.run = function () {
			var requestManager = this,
			originalCallback   = requestManager.requests[0].complete;

			requestManager.requests[0].complete = function () {
				if ( typeof originalCallback === 'function' ) {
					originalCallback();
				}

				requestManager.requests.shift();

				if ( requestManager.requests.length > 0 ) {
					requestManager.run();
				}
			};

			$.ajax( this.requests[0] );
		};

		/**
		 * Add product to quote list.
		 */
		RfqFrontendHandler.prototype.onAddToQuote = function ( e ) {
			var $thisbutton = $( this );

			if ( ! $thisbutton.attr( 'data-product_id' ) ) {
				return true;
			}

			e.preventDefault();

			$thisbutton.addClass( "is-busy" );
			$thisbutton.removeClass( 'added' );
			$thisbutton.addClass( 'loading' );

			var button_wrap = $thisbutton.parents( '.wpheka-add-to-quote-button-wrapper' ),
			prod_id         = $thisbutton.attr( 'data-product_id' );

			// find the cart form
			if ( $thisbutton.closest( '.cart' ).length ) {
				$form = $thisbutton.closest( '.cart' );
			} else if ( button_wrap.siblings( '.cart' ).first().length ) {
				$form = button_wrap.siblings( '.cart' ).first();
			} else if ( jQuery( '.composite_form' ).length ) {
				$form = jQuery( '.composite_form' );
			} else {
				$form = jQuery( '.cart:not(.in_loop)' ); // not(in_loop) for color and label
			}

			var add_to_quote_data = $form.serialize();

			var is_variable_product = $form.find( '.variations select' );

			if ( is_variable_product.length ) {
				var variation_is_unavailable   = $form.find( '.wc-variation-is-unavailable' );
				var variation_selection_needed = $form.find( '.wc-variation-selection-needed' );

				if ( variation_is_unavailable.length ) {
					window.alert( wpheka_rfq_frontend_params.i18n_unavailable_text );
					$thisbutton.removeClass( "is-busy" );
					$thisbutton.removeClass( 'loading' );
					return false;
				} else if ( variation_selection_needed.length ) {
					window.alert( wpheka_rfq_frontend_params.i18n_make_a_selection_text );
					$thisbutton.removeClass( "is-busy" );
					$thisbutton.removeClass( 'loading' );
					return false;
				}
			}

			add_to_quote_data += '&action=wpheka_add_to_quote&product_id=' + prod_id + '&security=' + wpheka_rfq_frontend_params.add_to_quote_nonce;
			
			$thisbutton.siblings( '.ajax-loading' ).show();

			if (add_to_quote_data.indexOf( 'add-to-cart' ) > 0) {
				add_to_quote_data = add_to_quote_data.replace( 'add-to-cart', 'wpheka-add-to-cart' );
			}

			e.data.RfqFrontendHandler.addRequest(
				{
					type: 'POST',
					url: wpheka_rfq_frontend_params.ajax_url.toString().replace( '%%endpoint%%', 'wpheka_add_to_quote' ),
					data: add_to_quote_data,
					success: function ( response ) {
						if ( ! response ) {
							return;
						}

						if ( response.result == 'true' || response.result == 'exists' ) {
							button_wrap.append( '<div class="wpheka_rfq_add_item_response' + prod_id + ' wpheka_rfq_add_item_response_message">' + response.message + '</div>' );
							button_wrap.append( '<div class="wpheka_rfq_add_item_browse-list' + prod_id + ' wpheka_rfq_add_item_browse_message"><a href="' + response.rfq_page_url + '">' + response.label_browse + '</a></div>' );
						} else if ( response.result == 'false' ) {
							window.alert( response.message );
						}

					},
					complete: function () {
						$thisbutton.siblings( '.ajax-loading' ).hide();
						$thisbutton.removeClass( "is-busy" );
						$thisbutton.removeClass( 'loading' );
						$thisbutton.addClass( 'added' );
						$thisbutton.hide();
					},
					error: function () {
						console.log( 'Something went wrong, Try again later!' );
						return;
					},
					dataType: 'json'
				}
			);

		};

		/**
		 * Remove product from quote list.
		 */
		RfqFrontendHandler.prototype.onRemoveFromQuote = function ( e ) {
			e.preventDefault();

			var $a    = jQuery( e.currentTarget );
			var $form = $a.parents( 'form' );

			$form.LoadingOverlay( "show" );

			var data = {
				action: 'remove_item_from_rfq_list',
				rfq_item_key: $a.attr( 'data-rfq_item_key' ),
				security: wpheka_rfq_frontend_params.add_to_quote_nonce
			};

			e.data.RfqFrontendHandler.addRequest(
				{
					type: 'POST',
					url: wpheka_rfq_frontend_params.ajax_url.toString().replace( '%%endpoint%%', 'remove_item_from_rfq_list' ),
					data: data,
					success: function ( response ) {
						if ( ! response ) {
							return;
						}

						if ( response.success ) {
							jQuery( '.wpheka-request-for-quote' ).empty();
							jQuery( '.wpheka-request-for-quote' ).append( response.data.html );

							if ( response.data.hide_message_form ) {
								if ( jQuery( "#wpheka-quote-request-form" ).length ) {
									jQuery( "#wpheka-quote-request-form" ).remove();
								}
							}
						}

					},
					complete: function () {
						$form.LoadingOverlay( "hide", true );
					},
					error: function () {
						console.log( 'Something went wrong, Try again later!' );
						return;
					},
					dataType: 'json'
				}
			);

		};

		/**
		 * Update quote list.
		 */
		RfqFrontendHandler.prototype.onUpdateQuoteList = function ( e ) {
			e.preventDefault();

			var $form = jQuery( this );

			$form.LoadingOverlay( "show" );

			var rfq_updated_data = $form.serialize();

			rfq_updated_data += '&action=update_rfq_list';

			e.data.RfqFrontendHandler.addRequest(
				{
					type: 'POST',
					url: wpheka_rfq_frontend_params.ajax_url.toString().replace( '%%endpoint%%', 'update_rfq_list' ),
					data: rfq_updated_data,
					success: function ( response ) {
						if ( ! response ) {
							return;
						}

						if ( response.success ) {
							jQuery( '.wpheka-request-for-quote' ).empty();
							jQuery( '.wpheka-request-for-quote' ).append( response.data.html );
						}

					},
					complete: function () {
						$form.LoadingOverlay( "hide", true );
					},
					error: function () {
						console.log( 'Something went wrong, Try again later!' );
						return;
					},
					dataType: 'json'
				}
			);

		};

		/**
		 * Send quote list.
		 */
		RfqFrontendHandler.prototype.onSendRequestList = function ( e ) {
			e.preventDefault();

			var $form = jQuery( this );

			$form.LoadingOverlay( "show" );

			var rfq_updated_data = $form.serialize();

			rfq_updated_data += '&action=send_rfq_list';

			e.data.RfqFrontendHandler.addRequest(
				{
					type: 'POST',
					url: wpheka_rfq_frontend_params.ajax_url.toString().replace( '%%endpoint%%', 'send_rfq_list' ),
					data: rfq_updated_data,
					success: function ( response ) {
						if ( ! response ) {
							return;
						}

						if ( response.success ) {
							jQuery( '.wpheka-request-for-quote' ).empty();
						}
						jQuery( '.wpheka-request-for-quote' ).append( '<div class="woocommerce-message" role="alert">' + response.data.message + '</div>' );

					},
					complete: function () {
						$form.LoadingOverlay( "hide", true );
					},
					error: function () {
						console.log( 'Something went wrong, Try again later!' );
						return;
					},
					dataType: 'json'
				}
			);

		};

		/**
		 * Init RfqFrontendHandler.
		 */
		new RfqFrontendHandler();
	}
);