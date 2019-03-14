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


    }


    ## THESE ARE TWEAKS FOR SURVEY_COMPLETE
    function redcap_survey_complete($project_id, $record, $instrument, $event_id, $group_id, $survey_hash, $response_id, $repeat_instance)
    {

        $survey_complete_tweaks = array(
            'hide_end_queue'       => 'hideEndQueue'
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
                    $("[name=submit-btn-saverecord]").hide();
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
                $("button:contains('Submit')").text(newval);
                $("tr.surveysubmit").css({"opacity": 1});
            });
        </script>
        <?php
    }


    // Hide the survey queue summary at the end of survey page
    function hideEndQueue()
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
        $global_setting = $this->getProjectSetting("global_" . $keyName);

        if ($global_setting) {
            $this->emDebug("enabling global $funcName");
            call_user_func_array(array($this, $funcName), empty($args) ? array($global_setting) : $args);
        } else {
            foreach ($this->settings as $settings) {
                if ($settings['survey_name'] == $instrument && $settings[$keyName]) {
                    $this->emDebug("enabling  $funcName on $instrument");
                    call_user_func_array(array($this, $funcName), empty($args) ? array($settings[$keyName]) : $args);
                }
            }
        }
    }

    // TODO: Build function to determine which are enabled
    function getEnabledTweaks() {
        foreach($this::SURVEY_PAGE_TOP_TWEAKS as $key=>$func) {
            if ($this->getProjectSetting('global_' . $key)) {
            }
        }
    }

}