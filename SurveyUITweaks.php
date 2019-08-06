<?php
namespace Stanford\SurveyUITweaks;

include_once ("emLoggerTrait.php");

use \REDCap;

/**
 *
 * A bunch of little CSS and JS tweaks that help enhance survey functionality
 * TODO: Make configuration available from the actual Survey Settings page
 *
 * Class SurveyUITweaks
 * @package Stanford\SurveyUITweaks
 */
class SurveyUITweaks extends \ExternalModules\AbstractExternalModule
{
    use emLoggerTrait;

    public $settings;   // Per survey subsettings

    function __construct()
    {
        parent::__construct();
        if ($this->getProjectId()) {
            // Load the project settings
            $this->settings = $this->getSubSettings('survey_tweaks');
        }
    }


    ## THESE ARE TWEAKS FOR SURVEY_PAGE_TOP
    function redcap_survey_page_top($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {

        $survey_page_top_tweaks = array(
            'remove_excess_td'     => 'removeExcessTd',
            'autoscroll'           => 'autoscroll',
            'hide_queue_corner'    => 'hideQueueCorner',
            'hide_font_resize'     => 'hideFontResize',
            'hide_submit_button'   => 'hideSubmitButton',
            'rename_submit_button' => 'renameSubmitButton',
            'hide_reset_button'    => 'hideResetButton'
        );

        foreach($survey_page_top_tweaks as $key=>$func) {
            $this->checkFeature($key, $func, $instrument);
        }

        $this->checkSurveyDuration($instrument);
        $this->checkMatrixRank($instrument);

    }


    ## THESE ARE TWEAKS FOR SURVEY_COMPLETE
    function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {
        $this->emDebug($instrument . "here");

        $survey_complete_tweaks = array(
            'hide_queue_end'       => 'hideQueueEnd'
        );

        foreach($survey_complete_tweaks as $key=>$func) {
            $this->checkFeature($key, $func, $instrument);
        }
    }


    ## ACTUAL TWEAK FUNCTIONS - ADD MORE TO YOUR HEART'S CONTENT!
    function checkSurveyDuration($instrument) {
        // Array of arrays of duration_field => field_name
        $duration_fields = array();
        $duration_field_settings = $this->getSubSettings('survey_duration_fields');
        foreach ($duration_field_settings as $setting) {
            $duration_fields[] = $setting['duration_field'];
        }

        // Dont do anything if we don't have any duration fields specified
        if (empty($duration_fields)) return false;

        // Filter duration fields by instrument
        $instrument_fields = REDCap::getFieldNames($instrument);
        $fields = array_intersect($duration_fields, $instrument_fields);

        // None of the fields are on the current form
        if (empty($fields)) return false;

        ?>
            <script>
                var SurveyDuration = SurveyDuration || {};
                SurveyDuration.fields = <?php echo json_encode($fields) ?>;
                <?php echo file_get_contents($this->getModulePath() . "js/SurveyDuration.js"); ?>
            </script>
        <?php
    }

    ## ACTUAL TWEAK FUNCTIONS - ADD MORE TO YOUR HEART'S CONTENT!
    function checkMatrixRank($instrument) {
        // Array of arrays of matrix_groups => field_name
        $matrix_groups  = array();
        $matrix_options = array();
        $field_settings = $this->getSubSettings('sortrank');

        foreach ($field_settings as $setting) {
            $matrix_groups[]                            = $setting['matrix_name'];
            $matrix_options[$setting['matrix_name']]    = array(
                 'show_rank_label'      => $setting['show_rank_label']
                ,'matrix_instructions'  => $setting['matrix_instructions']
                ,'randomize_options'    => $setting['randomize_options']
            );
        }

        // Dont do anything if we don't have any matrix_groups specified
        if (empty($matrix_groups)) return false;

        // Get Instrument Data Dictionary
        $instrument_dict_json =  REDCap::getDataDictionary($this->getProjectId(), "json", False, Null, array($instrument));
        $instrument_dict =  json_decode($instrument_dict_json,true);

        // IF Survey in Progress Will need to Get State... Scrape from existing?
        // Or Download here and redo... seems wasteful to do a duplicate

        $error_arr  = array();
        $matrix_arr = array();
        foreach($instrument_dict as $field){
            $mtx_grp = $field["matrix_group_name"];

            if(!empty($mtx_grp) && in_array($mtx_grp, $matrix_groups)){
                //Iterate through data dict. And look for Desired Matrix Ranks
                if(!array_key_exists($mtx_grp, $error_arr)) $error_arr[$mtx_grp] = array();

                //Verify field type = radio
                //Verify matrix_ranking = y
                if(empty($error_arr[$mtx_grp])) {
                    if ($field["field_type"] !== "radio") {
                        $error_arr[$mtx_grp][] = "field_type must be 'radio'";
                    }
                    if ($field["matrix_ranking"] !== "y") {
                        $error_arr[$mtx_grp][] = "matrix_ranking must be 'y'";
                    }
                }

                if(empty($error_arr[$mtx_grp])){
                    if(!array_key_exists($mtx_grp, $matrix_arr)) $matrix_arr[$mtx_grp] = array();
                    $matrix_arr[$mtx_grp][] = $field;
                }
            }
        }
        //LOG ANY ERRORS
        if(!empty($error_arr)){
            foreach($error_arr as $err_mtx_grp => $errs){
                if(!empty($errs)){
                    REDCap::logEvent("[". $this->PREFIX . "] Invalid Rank Matrix Config", "$err_mtx_grp : " . implode(", ", $errs));
                }
            }
        }

        // None of the matrix_groups are on the current form
        if (empty($matrix_arr)) return false;

        $rank_matrices = array();
        foreach($matrix_arr as $mtx_grp => $fields){
            $rank_matrices[$mtx_grp] = array();

            $field_names    = array();
            $field_labels   = array();
            foreach($fields as $field){
                $field_names[]  = $field["field_name"];
                $field_labels[] = $field["field_label"];
            }

            $choice_split = explode(" | ", $fields[0]["select_choices_or_calculations"]);
            $choice_arr = array();
            foreach($choice_split as $split){
                list($k,$v) = explode(",",$split, 2);
                $choice_arr[trim($k)] = trim($v);
            }

            $rank_matrices[$mtx_grp]["choices"] = $choice_arr;
            $rank_matrices[$mtx_grp]["header"]  = $fields[0]["section_header"];
            $rank_matrices[$mtx_grp]["names"]   = $field_names;
            $rank_matrices[$mtx_grp]["labels"]  = $field_labels;
            $rank_matrices[$mtx_grp]["options"] = $matrix_options[$mtx_grp];
        }


//        $this->emDebug($rank_matrices);

        ?>
        <style>
            .sort_rank_container {
                padding:2vh;
                overflow:hidden;
            }
            .draggable{
                display:inline-block;
                float:left;
                border:1px solid #efefef;
                border-radius:5px;
                padding:.25%;
                width:48%;
            }
            .draggable:last-child{
                margin-left:2%;
            }
            .draggable ul{
                margin:0;
                padding:0;
                list-style:none;
                position:relative;
                z-index:1;
            }
            .draggable li{
                display: block;
                border: 1px solid #ccc;
                margin: 1vh;
                padding: 2vh;
                border-radius: 5px;
                cursor: pointer;
                min-height: 2vh;
                background:#fff;
            }
            .draggable li:hover{
                background:#efefef;
            }
            .draggable p.alert{
                border:1px dashed #ccc !important;
                margin:20px;
                position:absolute;
                z-index:0;
            }
        </style>
        <script>
            var MatrixRanking       = MatrixRanking || {};
            MatrixRanking.config    = <?php echo json_encode($rank_matrices) ?>;
            <?php
                //THE ORDER OF THE FOLLOWING 3 JS files MUST BE LIKE SO.
                echo file_get_contents($this->getModulePath() . "js/Sortable.min.js");
                echo file_get_contents($this->getModulePath() . "js/jquery-sortable.js");
                echo file_get_contents($this->getModulePath() . "js/MatrixRanking.js");
            ?>
        </script>
        <?php
    }

    function removeExcessTd()
    {
        //remove the excess TD on left if $question_auto_numbering on
        global $question_auto_numbering;
        if ($question_auto_numbering == 0) {
            ?>
            <style>
                td.questionnum, td.questionnummatrix {
                    display: none !important;
                }

                /* When we clean up the left space, we can extend enhanced choice options
                to be full width for a better appearance */
                div.enhancedchoice label { width: 100% }

            </style>
            <?php
        }
    }


    function hideSubmitButton()
    {
        // TODO: Change to CSS fix instead of JS
        ?>
            <style>
                tr.surveysubmit {
                    opacity: 0;
                }
            </style>
            <script type="text/javascript">
                $(document).ready(function () {
                    $("button:contains('Submit')").hide();
                    $("tr.surveysubmit").css({"opacity":1});
                });
            </script>
        <?php
    }


    function hideQueueCorner()
    {
        ?>
        <style>
            #return_corner, #survey_queue_corner {
            display: none !important;
                    }
        </style>
        <?php
    }


    function hideFontResize()
    {
        ?>
        <style>
            #changeFont .nowrap {
                display: none;
            }
        </style>
        <?php
    }


    function autoscroll()
    {
        ?>
        <style>
            #autoscroll         { background-color: #666; display:inline-block; color: #fff !important; }
            #autoscroll.enabled { background-color: #8C1515; }
        </style>
        <?php
        echo "<script>" . file_get_contents($this->getModulePath() . "/js/autoscroll.js") . "</script>";
    }


    function renameSubmitButton($name)
    {
        ?>
        <style>
            tr.surveysubmit {
                opacity: 0;

            }
        </style>
        <script type="text/javascript">
            $(document).ready(function () {
                var newval = "<?php echo $name ?>";
                //var width = $(newval).width();
                $("button:contains('Submit')").text(newval);
                $("tr.surveysubmit").css({"opacity": 1});
                //$("button:contains(newval)").css({"min-width": width, "max-width": width + 50});
                //$('[name = "submit-btn-saverecord"]').attr('style', 'max-width: 100% !important; color: #800000; width: 100%;'); //bad fix


                // need to figure out how to dynamically change the size and add rules for keeping the "Next Page" buttons the original size
                if($("button:contains(newval)")){
                    //$('#surveytitle').text("Here");
                    $('[name = "submit-btn-saverecord"]').attr('style', 'min-width: 140px; color: #800000; width: 100%; padding-left: 10px !important; padding-right: 10px !important; white-space: initial !important');
                }
            });
        </script>
        <?php
    }


    // Hide the survey queue summary at the end of survey page
    function hideQueueEnd()
    {
        ?>
            <style>
                #survey_queue {
                    display: none !important;
                }
            </style>
        <?php
    }

    // Hide the reset links for radio questions
    function hideResetButton()
    {
        ?>
        <style>
            .smalllink { display:none !important; }
        </style>
        <script type="text/javascript">
            // $(document).ready(function () {
            //     $(".smalllink").remove();
            // });
        </script>
        <?php
    }




    /**
     * A helper that assumes the keyNames for global or survey-specific are the same
     * @param       $keyName        // This is the name of the key for the survey-specific setting (should be checkbox)
     * @param       $funcName       // This is the function to call if true
     * @param       $instrument     // This is the current instrument
     * @param array $args           // This is an optional array of parameters to pass to the function
     *                              // otherwise the return value from the keyName setting is passed to the function
     */
    function checkFeature($keyName, $funcName, $instrument, $args = array())
    {
        $globalKey = 'global_' . $keyName;
        $projectSettings = $this->getProjectSettings();
        $keyFound = array_key_exists($globalKey, $projectSettings);

        $global_setting = $this->getProjectSetting($globalKey);

        if ($global_setting) {
            $this->emDebug("enabling global $funcName");
            call_user_func_array(array($this, $funcName), empty($args) ? array($global_setting) : $args);
        } else {
            foreach ($this->settings as $settings) {
                if (array_key_exists($keyName, $settings)) $keyFound=true;
                if ($settings['survey_name'] == $instrument && $settings[$keyName]) {
                    $this->emDebug("enabling  $funcName on $instrument");
                    call_user_func_array(array($this, $funcName), empty($args) ? array($settings[$keyName]) : $args);
                }
            }
        }
        if (!$keyFound) $this->emError("Unable to find key $keyName in settings");
    }

    // TODO: Build function to determine which are enabled
    function getEnabledTweaks() {
        foreach($this::SURVEY_PAGE_TOP_TWEAKS as $key=>$func) {
            if ($this->getProjectSetting('global_' . $key)) {
            }
        }
    }

}