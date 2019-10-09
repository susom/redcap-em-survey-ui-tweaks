// THIS IS THE SURVEYDURATION JS FILE - ANDY MARTIN - STANFORD


// ADAPTED CLOCK FROM https://github.com/mrdoob/three.js/blob/master/src/core/Clock.js
SurveyDuration.Clock = function( autoStart ) {

	this.autoStart = true; //( autoStart !== undefined ) ? autoStart : true;
	this.startTime = 0;
	this.oldTime = 0;
	this.elapsedTime = 0;
	this.running = false;

	if(this.autoStart) this.start();

};

Object.assign( SurveyDuration.Clock.prototype, {

	start: function () {

		this.startTime = ( typeof performance === 'undefined' ? Date : performance ).now(); // see #10732

		this.oldTime = this.startTime;
		this.elapsedTime = 0;
		this.running = true;

	},

	stop: function () {

		this.getElapsedTime();
		this.running = false;
		this.autoStart = false;

	},

	getElapsedTime: function () {

		this.getDelta();
		return this.elapsedTime;

	},

	getDelta: function () {

		var diff = 0;

		if ( this.autoStart && ! this.running ) {

			this.start();
			return 0;

		}

		if ( this.running ) {

			var newTime = ( typeof performance === 'undefined' ? Date : performance ).now();

			diff = ( newTime - this.oldTime ) / 1000;
			this.oldTime = newTime;

			this.elapsedTime += diff;

		}

		return diff;

	}

} );


// Go through and add CSS to hide these columns
SurveyDuration.hideFields = function() {

    $.each(this.fields, function(i,field){
       $("head").append("<style type='text/css'>#" + field + "-tr { display: none; }</style>");
    });

};


// Add each field into SurveyDuration object
SurveyDuration.startTimers = function() {

    if (!this.field) this.field = {};

    $.each(this.fields, function(i,field){

		const tr = $('#' + field + '-tr');

        // If the field is on the page, then set the timer for it
        if (tr.length) {
			const f = SurveyDuration.field[field] = {};
	        f.tr  = tr;
			f.ele = $('input[name="' + field + '"]', f.tr);
			f.initialValue = parseFloat(f.ele.val()) || 0;
			f.clock = new SurveyDuration.Clock(); //f.initialValue);
		}
    });
};


// Take the original value and update it with the new value
SurveyDuration.updateForm = function() {
    $.each(this.field, function(field, f) {
        const newTime = ( f.initialValue + f.clock.getElapsedTime() ).toPrecision(4);
        // console.log("Prev time was " + f.initialValue, "New time is: " + newTime);
        f.ele.val( newTime );
    });
};


// Hide the destination fields with CSS so they 'cleanly' don't show up.
SurveyDuration.hideFields();


// Once the page is done rendering, start up the timers
$(document).ready(function() {

    // Lets start the timers
    SurveyDuration.startTimers();

    // Proxy the original data entry submit function so we can update the duration stamps
    SurveyDuration.dataEntrySubmit = formSubmitDataEntry;
    formSubmitDataEntry = function () {
        SurveyDuration.updateForm();
        return SurveyDuration.dataEntrySubmit();
    };

});
