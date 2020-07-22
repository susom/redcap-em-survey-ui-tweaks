
var emAutoscroll = {
    scrollToNextTr: function(){
        if ( $('#autoscroll').hasClass('enabled') ) {
            // Skip Matrix Radios
            if ($(this).closest('td').hasClass('choicematrix')) return;
            // Get the current tr
            currentTr = $(this).parentsUntil('tr').parent();
            // Add a slight delay for branching logic to file and new TRs to be displayed...
            var timeoutId = window.setTimeout(function() {
                if (nextTr = $(currentTr).nextAll('tr:visible').first()) {
                    $("html, body").animate({
                        scrollTop: $(nextTr).offset().top - 10
                    }, 500);
                }
            },100,currentTr);
        }
    },

    toggleAutoscroll: function () {
        var status = $('#autoscroll').hasClass('enabled');
        if (status) {
            $('#autoscroll').removeClass('enabled').text("Autoscroll Off");
            setCookie('autoscroll','off',365);
        } else {
            $('#autoscroll').addClass('enabled').text("Autoscroll On");
            setCookie('autoscroll','on',365);
        }
    },

    init: function () {
        //Enable radios
        $('#questiontable tr input[type="radio"]').bind('click',emAutoscroll.scrollToNextTr);

        // Enable Selects
        $('#questiontable tr select').bind('change',emAutoscroll.scrollToNextTr);

        // Add Button in corner to toggle feature
        var btn = $('<button class="btn btn-xs enabled" id="autoscroll">AutoScroll On</button>').bind('click',emAutoscroll.toggleAutoscroll);

        // Set default state to off if cookie is present
        if (getCookie('autoscroll') === 'off') btn.removeClass('enabled').text("Autoscroll Off");

        // Render the botton on the page
        if ($('#changeFont').length) {
            // Survey
            $('#changeFont').prepend(btn);//.bind('click',emAutoscroll.toggleAutoscroll());
        } else if ($('#pdfExportDropdownTrigger').length) {
            // Data entry forms
            $('#pdfExportDropdownTrigger').after(btn);//.bind('click',emAutoscroll.toggleAutoscroll());
        }
    }
};


$(document).ready(function() {
    // Add a delay so that text size change can complete being rendered
    setTimeout(function() {
        emAutoscroll.init();
    },200);
});