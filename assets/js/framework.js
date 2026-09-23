/**
 * SocialBUMP Tweaks framework: field behaviours shared by every module screen.
 *
 * Colour fields: the swatch shows whatever the text box resolves to on this
 * page, including var(--name). Picking with the swatch writes a hex back.
 * Fields marked data-sb-tweaks-hide-when hide while their checkbox is ticked.
 * Select all and Select none set each tick with a real DOM change event,
 * because save-state.js listens with addEventListener and a jQuery trigger
 * never reaches it.
 */
( function ( $ ) {
	/**
	 * Resolve any CSS colour (hex, rgb, hsl, var) to a hex, or null if it can't be.
	 * A variable that isn't defined here inherits the sentinel colour, so it's caught.
	 */
	function resolveHex( value ) {
		if ( ! value ) {
			return null;
		}

		var holder = document.createElement( 'span' );
		var probe  = document.createElement( 'span' );

		holder.style.color   = 'rgb(1, 2, 3)';
		holder.style.display = 'none';
		holder.appendChild( probe );
		document.body.appendChild( holder );

		probe.style.color = value;

		var accepted = probe.style.color !== '';
		var computed = window.getComputedStyle( probe ).color;

		holder.parentNode.removeChild( holder );

		var m = accepted ? computed.match( /rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)/ ) : null;

		if ( ! m || ( m[1] === '1' && m[2] === '2' && m[3] === '3' ) ) {
			return null;
		}

		return '#' + [ m[1], m[2], m[3] ].map( function ( n ) {
			return ( '0' + parseInt( n, 10 ).toString( 16 ) ).slice( -2 );
		} ).join( '' );
	}

	$( function () {
		$( '.sb-tweaks-colour' ).each( function () {
			var $swatch = $( this ).find( '.sb-tweaks-colour__swatch' );
			var $value  = $( this ).find( '.sb-tweaks-colour__value' );

			var sync = function () {
				var hex = resolveHex( $.trim( $value.val() ) );

				if ( hex ) {
					$swatch.val( hex );
				}
			};

			$swatch.on( 'input change', function () {
				$value.val( this.value );
			} );

			$value.on( 'input', sync );
			sync();
		} );

		// Fields that hide while another checkbox is ticked.
		$( '[data-sb-tweaks-hide-when]' ).each( function () {
			var $field = $( this );
			var $box   = $( '#' + $field.data( 'sb-tweaks-hide-when' ) );

			if ( ! $box.length ) {
				return;
			}

			var sync = function () {
				$field.toggle( ! $box.prop( 'checked' ) );
			};

			$box.on( 'change', sync );
			sync();
		} );

		// A card lights up and dims as its switch is flipped.
		$( document ).on( 'change', '.sb-tweaks-card .sb-tweaks-switch input', function () {
			$( this ).closest( '.sb-tweaks-card' ).toggleClass( 'is-on', this.checked );
		} );

		// Select all and Select none on a long checklist.
		$( document ).on( 'click', '[data-sb-tweaks-check]', function () {
			var wanted = $( this ).data( 'sb-tweaks-check' ) === 'all';
			var $field = $( this ).closest( '.sb-tweaks-field' );

			$field.find( '.sb-tweaks-checklist input[type="checkbox"]' ).each( function () {
				if ( this.disabled || this.checked === wanted ) {
					return;
				}

				this.checked = wanted;
				this.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
		} );
	} );
} )( jQuery );
