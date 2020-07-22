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
                        scrollTop: $(nextTr).offset().top
                    }, 300);
                }
            },100,currentTr);
        }
    },

    toggleAutoscroll: function () {
        var status = $('#autoscroll').hasClass('enabled');
        if (status) {
            $('#autoscroll').removeClass('enabled').text("Autoscroll Off");
            setCookie('autoscroll','-1',365);
        } else {
            $('#autoscroll').addClass('enabled').text("Autoscroll On");
            setCookie('autoscroll','1',365);
        }

    },

    init: function () {
        //Enable radios
        $('#questiontable tr input[type="radio"]').bind('click',emAutoscroll.scrollToNextTr);
        // Enable Selects
        $('#questiontable tr select').bind('change',emAutoscroll.scrollToNextTr);

        // Add Button in corner to toggle feature
        // On 2020-07-23 noticed that there is an apparent conflict
        var btn = $('<button class="btn btn-xs enabled" id="autoscroll">AutoScroll On</button>').bind('click',emAutoscroll.toggleAutoscroll);
        if ($('#changeFont').length) {
            // Survey
            setTimeout(function() {
                $('#changeFont').prepend(btn).bind('click',emAutoscroll.toggleAutoscroll());
            }, 200);
        } else if ($('#pdfExportDropdownTrigger').length) {
            // Data entry forms
            $('#pdfExportDropdownTrigger').after(btn).bind('click',emAutoscroll.toggleAutoscroll());
        }
        if (getCookie('autoscroll') == -1) emAutoscroll.toggleAutoscroll();
    }
};


$(document).ready(function() {

    emAutoscroll.init();
});