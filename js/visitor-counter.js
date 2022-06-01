( function( $ ) {

    window.VisitorCounter = window?.VisitorCounter || {

        /**
         * @type {object} Temp storage object
         */
        _temp: {},

        /**
         * Get a PHP variable.
         *
         * @param varName
         * @returns {*}
         */
        getVar( varName ) {
            const vars = window?.VisitorCounterVars;
            return vars?.[ varName ];
        },

        /**
         * Make ajax request to server, store response in temp object.
         *
         * @returns {Promise<unknown>}
         */
        request() {
            return new Promise( ( accept, reject ) => {
                const url = this.getVar( 'ajaxUrl' );
                const action = this.getVar( 'ajaxAction' );

                if ( url && action ) {
                    $.ajax( {
                        url: url,
                        data: {
                            action: action
                        },
                        cache: false
                    } ).then( response => {
                        this._temp.count = response.data;
                        accept( response );
                    } );
                } else {
                    reject( 'Visitor counter ajax url or action not available.' );
                }
            } );
        },

        /**
         * Get the count value.
         *
         * @returns {number}
         */
        getCount() {
            return parseInt( this._temp?.count || 0 );
        },

        /**
         * Refresh data continuously.
         *
         * @param {callback} callback
         * @param {number} timeout
         */
        refresh( callback, timeout ) {
            this._temp.lastRefreshed = Date.now();

            this.request().then( () => {
                const now = Date.now();

                callback.apply( this );

                const timeoutDiff = now - this._temp.lastRefreshed;

                // If request took longer than timeout, run next refresh instantly.
                if ( timeout && timeoutDiff >= timeout ) {
                    this.refresh( callback, timeout );
                }

                // Request was quicker than timeout, queue next refresh call.
                else {
                    setTimeout( () => {
                        this.refresh( callback, timeout );
                    }, timeout - timeoutDiff );
                }
            } );
        }
    };

    // Initiate refresh loop
    VisitorCounter.refresh( function() {
        $( '#customer_count span' ).text( VisitorCounter.getCount() );					
    }, 2000 );	
							
} )( jQuery );
