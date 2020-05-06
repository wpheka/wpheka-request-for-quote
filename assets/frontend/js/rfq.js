jQuery(
	function ( $ ) {

		if ( typeof wpheka_rfq_shop_params === 'undefined' ) {
			return false;
		}

		/**
		 * AddToQuoteShopHandler class.
		 */
		var AddToQuoteShopHandler = function () {
			this.requests   = [];
			this.addRequest = this.addRequest.bind( this );
			this.run        = this.run.bind( this );

			$( document.body )
			.on( 'click', '.add-to-quote-loop', { AddToQuoteShopHandler: this }, this.onAddToQuote );
		};

		/**
		 * Add add to quote event.
		 */
		AddToQuoteShopHandler.prototype.addRequest = function ( request ) {
			this.requests.push( request );

			if ( 1 === this.requests.length ) {
				this.run();
			}
		};

		/**
		 * Run add to quote events.
		 */
		AddToQuoteShopHandler.prototype.run = function () {
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
		AddToQuoteShopHandler.prototype.onAddToQuote = function ( e ) {
			var $thisbutton = $( this );

			if ( ! $thisbutton.attr( 'data-product_id' ) ) {
				return true;
			}

			$thisbutton.addClass( "is-busy" );

			var prod_id     = $thisbutton.attr( 'data-product_id' );
			var button_wrap = $thisbutton.parent( '.wpheka-add-to-quote-button-wrapper' );

			e.preventDefault();

			var data = {};

			// Fetch changes that are directly added by calling $thisbutton.data( key, value )
			$.each(
				$thisbutton.data(),
				function ( key, value ) {
					data[ key ] = value;
				}
			);

			// Fetch data attributes in $thisbutton. Give preference to data-attributes because they can be directly modified by javascript
			// while `.data` are jquery specific memory stores.
			$.each(
				$thisbutton[0].dataset,
				function ( key, value ) {
					data[ key ] = value;
				}
			);

			data['action'] = 'wpheka_add_to_quote_shop';

			$thisbutton.siblings( '.ajax-loading' ).show();

			e.data.AddToQuoteShopHandler.addRequest(
				{
					type: 'POST',
					url: wpheka_rfq_shop_params.ajax_url.toString().replace( '%%endpoint%%', 'wpheka_add_to_quote_shop' ),
					data: data,
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
		 * Init AddToQuoteShopHandler.
		 */
		new AddToQuoteShopHandler();
	}
);
