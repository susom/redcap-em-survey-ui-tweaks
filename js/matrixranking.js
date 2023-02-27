//Hack for IE because its does not support assign
if (typeof Object.assign != 'function') {
    Object.assign = function (target) {
        'use strict';
        if (target == null) {
            throw new TypeError('Cannot convert undefined or null to object');
        }

        target = Object(target);
        for (var index = 1; index < arguments.length; index++) {
            var source = arguments[index];
            if (source != null) {
                for (var key in source) {
                    if (Object.prototype.hasOwnProperty.call(source, key)) {
                        target[key] = source[key];
                    }
                }
            }
        }
        return target;
    };
}

// THIS IS THE MatrixRank JS FILE - ANDY MARTIN - STANFORD

MatrixRanking = Object.assign( MatrixRanking, {

    init: function () {
        //PARSE the MatrixRank.config to Tweak Matrix UI from radio buttons to Drag n Drop
        var matrix_rank_names = Object.keys(this.config);

        this.hideDefault(matrix_rank_names);

        this.checkState(matrix_rank_names);

        for(var mtx_grp in this.config){
            this.constructNewUI(mtx_grp, this.config[mtx_grp]);
        }
        return;
    },

    hideDefault: function(og_mtx_grp){
        //HIDE DEFAULT REDCAP IMPLEMENTATION OF THIS MATRIX OFF SCREEN (WE STILL NEED THEIR REDCAP MECHANISMS/FUNCTIONALITY
        for(var i in og_mtx_grp){
            var og_mtx_name 	= og_mtx_grp[i];
            var sortrank_mtx_tr = $("tr[mtxgrp='"+og_mtx_name+"']");
            sortrank_mtx_tr.css("opacity",0).css("position","absolute").css("left","-5000px");
        }
        return;
    },

    constructNewUI : function(mtx_grp, mtx_grp_config){
        var use_rank_label 			= mtx_grp_config["options"]["show_rank_label"];
        var randomize_options 		= mtx_grp_config["options"]["randomize_options"];
        var show_mtx_instructions 	= mtx_grp_config["options"]["matrix_instructions"];

        //CREATE SOME UNIQUE VARS TO USE FOR THE NEW UI
        var sort_order_unique 		= "sort_order_" + mtx_grp;
        var sort_rank_id			= "sort_rank_" + mtx_grp;
        var sort_rank_target_id 	= "sort_rank_target_" + mtx_grp;


        //CREATE NEW HTML ELEMENTS TO INJECT INTO THE DOM FOR NEW UI
        var sort_rank_list_1 		= $("<ul>").addClass(sort_order_unique).attr("id",sort_rank_id);
        var sort_rank_list_2 		= $("<ul>").addClass(sort_order_unique).attr("id",sort_rank_target_id);
        var draggable_div_1 		= $("<div>").addClass("draggable").append(sort_rank_list_1);
        var draggable_div_2 		= $("<div>").addClass("draggable").append(sort_rank_list_2);
        var sort_rank_container 	= $("<td>").addClass("sort_rank_container").attr("colspan",3);
        sort_rank_container.append(draggable_div_1);
        sort_rank_container.append(draggable_div_2);

        var list_count 	= mtx_grp_config["labels"].length;
        draggable_div_1.css("min-height",(list_count*7)+4+"vh");
        draggable_div_2.css("min-height",(list_count*7)+4+"vh");
        sort_rank_list_1.css("min-height",(list_count*7)+"vh");
        sort_rank_list_2.css("min-height",(list_count*7)+"vh");

        //MAKE SURE THIS IS NOT AN IN PROGRESS "Save and Return" Survey that already has values
        var saved_values 	= mtx_grp_config.hasOwnProperty("saved_values") ? Object.values(mtx_grp_config["saved_values"]) : [];
        var un_checked 		= mtx_grp_config["names"].filter(function(e){
            return this.indexOf(e) < 0;
        }, saved_values);

        var stored_nam_lab 	= {};
        var randomize_opts 	= [];

        for (var i in mtx_grp_config["names"]){
            if(un_checked.indexOf(mtx_grp_config["names"][i]) < 0){
                stored_nam_lab[mtx_grp_config["names"][i]] = mtx_grp_config["labels"][i];
                continue;
            }

            var input_prefix 	= "#mtxopt-";
            var mtx_pfx 		= input_prefix + mtx_grp_config["names"][i] + "_";
            var label 			= $("<li>").text(mtx_grp_config["labels"][i]).attr("data-checkgrp",mtx_pfx).attr("data-fieldname", mtx_grp_config["names"][i]).addClass("draggable_" + mtx_grp_config["names"][i]);

            // if branched hidden, then will need to hide in the new UI as well
            if(this.branched_hidden.indexOf(mtx_grp_config["names"][i]) > -1){
                label.addClass("branch_hidden");
            }

            if(randomize_options){
                randomize_opts.push(label);
            }else{
                sort_rank_list_1.append(label);
            }
        }
        if(randomize_opts.length){
            for(var i = randomize_opts.length-1; i>=0; i--){
                sort_rank_list_1.append(randomize_opts.splice(Math.floor(Math.random()*randomize_opts.length), 1));
            }
        }

        //IF RESUMING SAVED SURVEY DO THIS
        if(saved_values.length){
            var saved_keys = Object.keys(mtx_grp_config["saved_values"]);
            var saved_vals = Object.values(mtx_grp_config["saved_values"]);
            for (var nam in saved_values){
                var input_prefix 	= "#mtxopt-";
                var field_name 		= saved_values[nam];
                var mtx_pfx 		= input_prefix + field_name + "_";
                var val_idx 		= saved_vals.indexOf(field_name);
                var rank_order 		= saved_keys[val_idx];
                var rank_badge 		= $("<span>").addClass("badge").addClass("badge-pill").addClass("badge-info").css("margin-right","5px").text(rank_order);
                var label 			= $("<li>").text(stored_nam_lab[field_name]).attr("data-checkgrp",mtx_pfx).attr("data-fieldname", field_name).prepend(rank_badge);

                sort_rank_list_2.append(label);
            }
        }

        //Best Way to Get the Header Row to Insert Our New Row
        var new_row 	= $("<tr>").append(sort_rank_container);
        var first_row 	= $("tr[mtxgrp='"+mtx_grp+"']:eq( 0 )");
        first_row.before(new_row);
        // first_row.hide();

        if(show_mtx_instructions && saved_values.length == 0){
            var mtx_instructions = $("<p>").addClass("alert alert-light").text(show_mtx_instructions).width(draggable_div_2.width()*.9);
            draggable_div_2.prepend(mtx_instructions);
        }

        //Need to unbind inline onclick for hidden input radios cause they do their own checking logic which blocks the reordering below
        $("tr[mtxgrp='"+mtx_grp+"'] .data.choicematrix input[type='radio']").removeAttr('onclick');

        //NOW SET THE UI AS "Sortable"
        $("#"+sort_rank_id+", #"+sort_rank_target_id+"").sortable({
            // See: (https://github.com/SortableJS/Sortable#options)

            group: sort_order_unique
            ,onEnd : function(evt){
                //always remove badges from left side
                $("#"+sort_rank_id+" .badge").remove();

                if($(evt.to).attr("id").indexOf("target") > -1){
                    //hide instructions if any
                    draggable_div_2.find("p.alert").hide();
                }else{
                    if(!$("#"+sort_rank_target_id+" li").length){
                        draggable_div_2.find("p.alert").show();
                    }
                }

                //FIRST UNCHECK ALL THE CURRENT ORDER TO AVOID THAT DOUBLE CHECKED ERROR MESSAGE
                $("tr[mtxgrp='"+mtx_grp+"'] .resetLinkParent .smalllink").click();

                //NOW ITERATE THROUGH CURRENT ORDER AND UPDATE ALL THE CLICK VALUES IN THE EXISTING MATRIX (that is sitting offscreen)
                $("#"+sort_rank_target_id+" li").each(function(idx){
                    var rank_order 	= idx + 1;
                    var mtx_pfx 	= $(this).data("checkgrp");
                    var mtx_order 	= mtx_pfx + rank_order;

                    if(use_rank_label){
                        $(this).find(".badge").remove();

                        if($(evt.to).attr("id").indexOf("target")){
                            var rank_badge = $("<span>").addClass("badge").addClass("badge-pill").addClass("badge-info").css("margin-right","5px").text(rank_order);
                            $(this).prepend(rank_badge);
                        }
                    }
                    setTimeout(function(){
                        // newest redcap version adds new UI / js to matrix ranking ... small 100ms timeout will allow this to wait oout RC check, and then reset rankings
                        $(mtx_order).click();
                    },100);
                });
            }
        });

        //FIRST matrix var is used to make the header , so just make it show whether a branch has been activated or not
        let matrix_header = "#" + mtx_grp_config["names"][0]+"-sh-tr";
        $(matrix_header).addClass("show_overide");
        return;
    },

    checkState: function(){
        this.branched_hidden = [];

        // Since REDCAP already Populating the Original Matrix with Values, Might be more efficient To just pull the values from there? And Put it in MatrixRanking.config
        //Loop through the Var names of the Relevant Matrix Rank inputs and see if they have value
        for (var i in this.config){
            var grp 			= this.config[i];
            var saved_values 	= {}
            for(var x in grp["names"]){
                var varname = grp["names"][x];
                var varval 	= $("input[name='"+varname+"']").val();
                if(varval){
                    saved_values[varval] = varname;
                }

                //for initial load on screen, will match the display visiblity (incase branching);
                var vartr = "#" + varname + "-tr";

                if($(vartr).length && !$(vartr).is(":visible")){
                    this.branched_hidden.push(varname);
                }

                $( vartr ).on( varname + '-visibility', function() {
                    var _this    = $( this );
                    setInterval( function() {
                        let varname = ".draggable_"+_this.attr("sq_id");
                        if( _this.is( ':hidden' ) ) {
                            $(varname).addClass("branch_hidden");
                        } else {
                            $(varname).removeClass("branch_hidden");
                        }
                    }, 300 );
                }).trigger( varname + '-visibility' );
            }

            //IF THERE IS A STORED STATE VALUE, IT WILL BE IN the Property "saved_values"
            if(Object.keys(saved_values).length){
                this.config[i]["saved_values"] = saved_values;
            }
        }
    }
} );


// Once the page is done rendering , Fire off The MatrixRank UI Swaps
$(document).ready(function() {
    //MatrixRanking Object contains all code for transforming matrix ranking UI
    MatrixRanking.init();


});
